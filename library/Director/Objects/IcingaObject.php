<?php

namespace Icinga\Module\Director\Objects;

use Exception;
use Icinga\Data\Filter\Filter;
use Icinga\Data\Filter\FilterChain;
use Icinga\Data\Filter\FilterExpression;
use Icinga\Exception\ProgrammingError;
use Icinga\Module\Director\CustomVariable\CustomVariables;
use Icinga\Module\Director\IcingaConfig\AssignRenderer;
use Icinga\Module\Director\Data\Db\DbObject;
use Icinga\Module\Director\Db\Cache\PrefetchCache;
use Icinga\Module\Director\Db;
use Icinga\Module\Director\Exception\NestingError;
use Icinga\Module\Director\IcingaConfig\ExtensibleSet;
use Icinga\Module\Director\IcingaConfig\IcingaConfig;
use Icinga\Module\Director\IcingaConfig\IcingaConfigRenderer;
use Icinga\Module\Director\IcingaConfig\IcingaConfigHelper as c;
use Icinga\Module\Director\IcingaConfig\IcingaLegacyConfigHelper as c1;
use Icinga\Module\Director\Repository\IcingaTemplateRepository;

abstract class IcingaObject extends DbObject implements IcingaConfigRenderer
{
    const RESOLVE_ERROR = '(unable to resolve)';

    protected $keyName = 'object_name';

    protected $autoincKeyName = 'id';

    /** @var bool Whether this Object supports custom variables */
    protected $supportsCustomVars = false;

    /** @var bool Whether there exist Groups for this object type */
    protected $supportsGroups = false;

    /** @var bool Whether this Object makes use of (time) ranges */
    protected $supportsRanges = false;

    /** @var bool Whether inheritance via "imports" property is supported */
    protected $supportsImports = false;

    /** @var bool Allows controlled custom var access through Fields */
    protected $supportsFields = false;

    /** @var bool Whether this object can be rendered as 'apply Object' */
    protected $supportsApplyRules = false;

    /** @var bool Whether Sets of object can be defined */
    protected $supportsSets = false;

    /** @var bool Whether this Object supports template-based Choices */
    protected $supportsChoices = false;

    /** @var bool If the object is rendered in legacy config */
    protected $supportedInLegacy = false;

    protected $rangeClass;

    protected $type;

    /* key/value!! */
    protected $booleans = array();

    // Property suffixed with _id must exist
    protected $relations = array(
        // property => PropertyClass
    );

    protected $relatedSets = array(
        // property => ExtensibleSetClass
    );

    protected $multiRelations = array(
        // property => IcingaObjectClass
    );

    protected $loadedMultiRelations = array();

    /**
     * Allows to set properties pointing to related objects by name without
     * loading the related object.
     *
     * @var array
     */
    protected $unresolvedRelatedProperties = array();

    protected $loadedRelatedSets = array();

    // Will be rendered first, before imports
    protected $prioritizedProperties = array();

    protected $propertiesNotForRendering = array(
        'id',
        'object_name',
        'object_type',
    );

    /**
     * Array of interval property names
     *
     * Those will be automagically munged to integers (seconds) and rendered
     * as durations (e.g. 2m 10s). Array expects (propertyName => renderedKey)
     *
     * @var array
     */
    protected $intervalProperties = array();

    /** @var  Db */
    protected $connection;

    private $vars;

    private $groups;

    private $imports;

    /** @var  IcingaTimePeriodRanges - TODO: generic ranges */
    private $ranges;

    private $shouldBeRemoved = false;

    private $resolveCache = array();

    private $cachedPlainUnmodified;

    private $templateResolver;

    protected static $tree;

    /**
     * @return Db
     */
    public function getConnection()
    {
        return $this->connection;
    }

    public function propertyIsBoolean($property)
    {
        return array_key_exists($property, $this->booleans);
    }

    public function propertyIsInterval($property)
    {
        return array_key_exists($property, $this->intervalProperties);
    }

    /**
     * Whether a property ends with _id and might refer another object
     *
     * @param $property string Property name, like zone_id
     *
     * @return bool
     */
    public function propertyIsRelation($property)
    {
        if ($key = $this->stripIdSuffix($property)) {
            return $this->hasRelation($key);
        } else {
            return false;
        }
    }

    protected function stripIdSuffix($key)
    {
        $end = substr($key, -3);

        if ('_id' === $end) {
            return substr($key, 0, -3);
        }

        return false;
    }

    public function propertyIsRelatedSet($property)
    {
        return array_key_exists($property, $this->relatedSets);
    }

    public function propertyIsMultiRelation($property)
    {
        return array_key_exists($property, $this->multiRelations);
    }

    public function listMultiRelations()
    {
        return array_keys($this->multiRelations);
    }

    public function getMultiRelation($property)
    {
        if (! $this->hasLoadedMultiRelation($property)) {
            $this->loadMultiRelation($property);
        }

        return $this->loadedMultiRelations[$property];
    }

    public function setMultiRelation($property, $values)
    {
        $this->getMultiRelation($property)->set($values);
        return $this;
    }

    private function loadMultiRelation($property)
    {
        if ($this->hasBeenLoadedFromDb()) {
            $rel = IcingaObjectMultiRelations::loadForStoredObject(
                $this,
                $property,
                $this->multiRelations[$property]
            );
        } else {
            $rel = new IcingaObjectMultiRelations(
                $this,
                $property,
                $this->multiRelations[$property]
            );
        }

        $this->loadedMultiRelations[$property] = $rel;
    }

    private function hasLoadedMultiRelation($property)
    {
        return array_key_exists($property, $this->loadedMultiRelations);
    }

    private function loadAllMultiRelations()
    {
        foreach (array_keys($this->multiRelations) as $key) {
            if (! $this->hasLoadedMultiRelation($key)) {
                $this->loadMultiRelation($key);
            }
        }

        ksort($this->loadedMultiRelations);
        return $this->loadedMultiRelations;
    }

    protected function getRelatedSetClass($property)
    {
        $prefix = '\\Icinga\\Module\\Director\\IcingaConfig\\';
        return $prefix . $this->relatedSets[$property];
    }

    protected function getRelatedSet($property)
    {
        if (! array_key_exists($property, $this->loadedRelatedSets)) {
            /** @var ExtensibleSet $class */
            $class = $this->getRelatedSetClass($property);
            $this->loadedRelatedSets[$property]
                 = $class::forIcingaObject($this, $property);
        }

        return $this->loadedRelatedSets[$property];
    }

    protected function relatedSets()
    {
        $sets = array();
        foreach ($this->relatedSets as $key => $class) {
            $sets[$key] = $this->getRelatedSet($key);
        }

        return $sets;
    }

    /**
     * Whether the given property name is a short name for a relation
     *
     * This might be 'zone' for 'zone_id'
     *
     * @param string $property Property name
     *
     * @return bool
     */
    public function hasRelation($property)
    {
        return array_key_exists($property, $this->relations);
    }

    protected function getRelationClass($property)
    {
        return __NAMESPACE__ . '\\' . $this->relations[$property];
    }

    protected function getRelationObjectClass($property)
    {
        return $this->relations[$property];
    }

    public function getRelated($property)
    {
        return $this->getRelatedObject($property, $this->{$property . '_id'});
    }

    protected function getRelatedObjectName($property, $id)
    {
        return $this->getRelatedObject($property, $id)->object_name;
    }

    protected function getRelatedObject($property, $id)
    {
        /** @var IcingaObject $class */
        $class = $this->getRelationClass($property);
        return $class::loadWithAutoIncId($id, $this->connection);
    }

    public function getResolvedRelated($property)
    {
        $id = $this->getSingleResolvedProperty($property . '_id');

        if ($id) {
            return $this->getRelatedObject($property, $id);
        }

        return null;
    }

    public function prefetchAllRelatedTypes()
    {
        foreach (array_unique(array_values($this->relations)) as $relClass) {
            /** @var static $class */
            $class = __NAMESPACE__ . '\\' . $relClass;
            $class::prefetchAll($this->getConnection());
        }
    }

    public static function prefetchAllRelationsByType($type, Db $db)
    {
        /** @var static $class */
        $class = self::classByType($type);
        /** @var static $dummy */
        $dummy = $class::create(array(), $db);
        $dummy->prefetchAllRelatedTypes();
    }

    /**
     * Whether this Object supports custom variables
     *
     * @return bool
     */
    public function supportsCustomVars()
    {
        return $this->supportsCustomVars;
    }

    /**
     * Whether there exist Groups for this object type
     *
     * @return bool
     */
    public function supportsGroups()
    {
        return $this->supportsGroups;
    }

    /**
     * Whether this Object makes use of (time) ranges
     *
     * @return bool
     */
    public function supportsRanges()
    {
        return $this->supportsRanges;
    }

    /**
     * Whether this object supports (command) Arguments
     *
     * @return bool
     */
    public function supportsArguments()
    {
        return $this instanceof ObjectWithArguments;
    }

    /**
     * Whether this object supports inheritance through the "imports" property
     *
     * @return bool
     */
    public function supportsImports()
    {
        return $this->supportsImports;
    }

    /**
     * Whether this object allows controlled custom var access through fields
     *
     * @return bool
     */
    public function supportsFields()
    {
        return $this->supportsFields;
    }

    /**
     * Whether this object can be rendered as 'apply Object'
     *
     * @return bool
     */
    public function supportsApplyRules()
    {
        return $this->supportsApplyRules;
    }

    /**
     * Whether this object supports 'assign' properties
     *
     * @return bool
     */
    public function supportsAssignments()
    {
        return $this->isApplyRule();
    }

    /**
     * Whether this object can be part of a 'set'
     *
     * @return bool
     */
    public function supportsSets()
    {
        return $this->supportsSets;
    }

    /**
     * Whether this object supports template-based Choices
     *
     * @return bool
     */
    public function supportsChoices()
    {
        return $this->supportsChoices;
    }

    public function setAssignments($value)
    {
        return IcingaObjectLegacyAssignments::applyToObject($this, $value);
    }

    /**
     * @codingStandardsIgnoreStart
     *
     * @param Filter|string $filter
     *
     * @throws ProgrammingError
     *
     * @return self
     */
    public function setAssign_filter($filter)
    {
        if (! $this->supportsAssignments() && $filter !== null) {
            if ($this->hasProperty('object_type')) {
                $type = $this->get('object_type');
            } else {
                $type = get_class($this);
            }

            throw new ProgrammingError(
                'I can only assign for applied objects or objects with native'
                . ' support for assigments, got %s',
                $type
            );
        }

        // @codingStandardsIgnoreEnd
        if ($filter instanceof Filter) {
            $filter = $filter->toQueryString();
        }

        return $this->reallySet('assign_filter', $filter);
    }

    /**
     * It sometimes makes sense to defer lookups for related properties. This
     * kind of lazy-loading allows us to for example set host = 'localhost' and
     * render an object even when no such host exists. Think of the activity log,
     * one might want to visualize a history host or service template even when
     * the related command has been deleted in the meantime.
     *
     * @return self
     */
    public function resolveUnresolvedRelatedProperties()
    {
        foreach ($this->unresolvedRelatedProperties as $name => $p) {
            $this->resolveUnresolvedRelatedProperty($name);
        }

        return $this;
    }

    protected function resolveUnresolvedRelatedProperty($name)
    {
        $short = substr($name, 0, -3);
        /** @var IcingaObject $class */
        $class = $this->getRelationClass($short);
        $object = $class::load(
            $this->unresolvedRelatedProperties[$name],
            $this->connection
        );

        $this->reallySet($name, $object->get('id'));
        unset($this->unresolvedRelatedProperties[$name]);
    }

    public function hasBeenModified()
    {
        if (parent::hasBeenModified()) {
            return true;
        }
        $this->resolveUnresolvedRelatedProperties();

        if ($this->supportsCustomVars() && $this->vars !== null && $this->vars()->hasBeenModified()) {
            return true;
        }

        if ($this->supportsGroups() && $this->groups !== null && $this->groups()->hasBeenModified()) {
            return true;
        }

        if ($this->supportsImports() && $this->imports !== null && $this->imports()->hasBeenModified()) {
            return true;
        }

        if ($this->supportsRanges() && $this->ranges !== null && $this->ranges()->hasBeenModified()) {
            return true;
        }

        if ($this instanceof ObjectWithArguments
            && $this->gotArguments()
            && $this->arguments()->hasBeenModified()
        ) {
            return true;
        }

        foreach ($this->loadedRelatedSets as $set) {
            if ($set->hasBeenModified()) {
                return true;
            }
        }

        foreach ($this->loadedMultiRelations as $rel) {
            if ($rel->hasBeenModified()) {
                return true;
            }
        }

        return false;
    }

    protected function hasUnresolvedRelatedProperty($name)
    {
        return array_key_exists($name, $this->unresolvedRelatedProperties);
    }

    protected function getRelationId($key)
    {
        if ($this->hasUnresolvedRelatedProperty($key)) {
            $this->resolveUnresolvedRelatedProperty($key);
        }

        return parent::get($key);
    }

    protected function getRelatedProperty($key)
    {
        $idKey = $key . '_id';
        if ($this->hasUnresolvedRelatedProperty($idKey)) {
            return $this->unresolvedRelatedProperties[$idKey];
        }

        if ($id = $this->get($idKey)) {
            /** @var IcingaObject $class */
            $class = $this->getRelationClass($key);
            $object = $class::loadWithAutoIncId($id, $this->connection);
            return $object->get('object_name');
        }

        return null;
    }

    public function get($key)
    {
        if (substr($key, 0, 5) === 'vars.') {
            $var = $this->vars()->get(substr($key, 5));
            if ($var === null) {
                return $var;
            } else {
                return $var->getValue();
            }
        }

        // e.g. zone_id
        if ($this->propertyIsRelation($key)) {
            return $this->getRelationId($key);
        }

        // e.g. zone
        if ($this->hasRelation($key)) {
            return $this->getRelatedProperty($key);
        }

        if ($this->propertyIsRelatedSet($key)) {
            return $this->getRelatedSet($key)->toPlainObject();
        }

        if ($this->propertyIsMultiRelation($key)) {
            return $this->getMultiRelation($key)->listRelatedNames();
        }

        return parent::get($key);
    }

    public function setProperties($props)
    {
        if (is_array($props)) {
            if (array_key_exists('object_type', $props) && key($props) !== 'object_type') {
                $type = $props['object_type'];
                unset($props['object_type']);
                $props = array('object_type' => $type) + $props;
            }
        }
        return parent::setProperties($props);
    }

    public function set($key, $value)
    {
        if ($key === 'vars') {
            $value = (array) $value;
            $unset = array();
            foreach ($this->vars() as $k => $f) {
                if (! array_key_exists($k, $value)) {
                    $unset[] = $k;
                }
            }
            foreach ($unset as $k) {
                unset($this->vars()->$k);
            }
            foreach ($value as $k => $v) {
                $this->vars()->set($k, $v);
            }
            return $this;
        } elseif (substr($key, 0, 5) === 'vars.') {
            //TODO: allow for deep keys
            $this->vars()->set(substr($key, 5), $value);
            return $this;
        } elseif ($this instanceof ObjectWithArguments
            && substr($key, 0, 10) === 'arguments.'
        ) {
            $this->arguments()->set(substr($key, 10), $value);
            return $this;
        }

        if ($this->propertyIsBoolean($key)) {
            return parent::set($key, $this->normalizeBoolean($value));
        }

        // e.g. zone_id
        if ($this->propertyIsRelation($key)) {
            return $this->setRelation($key, $value);
        }

        // e.g. zone
        if ($this->hasRelation($key)) {
            return $this->setUnresolvedRelation($key, $value);
        }

        if ($this->propertyIsMultiRelation($key)) {
            $this->setMultiRelation($key, $value);
            return $this;
        }

        if ($this->propertyIsRelatedSet($key)) {
            $this->getRelatedSet($key)->set($value);
            return $this;
        }

        if ($this->propertyIsInterval($key)) {
            return parent::set($key, c::parseInterval($value));
        }

        return parent::set($key, $value);
    }

    private function setRelation($key, $value)
    {
        if ((int) $key !== (int) $this->$key) {
            unset($this->unresolvedRelatedProperties[$key]);
        }
        return parent::set($key, $value);
    }

    private function setUnresolvedRelation($key, $value)
    {
        if (strlen($value) === 0) {
            unset($this->unresolvedRelatedProperties[$key . '_id']);
            return parent::set($key . '_id', null);
        }

        $this->unresolvedRelatedProperties[$key . '_id'] = $value;
        return $this;
    }

    protected function setRanges($ranges)
    {
        $this->ranges()->set((array) $ranges);
        return $this;
    }

    protected function getRanges()
    {
        return $this->ranges()->getValues();
    }

    protected function normalizeBoolean($value)
    {
        if ($value === 'y' || $value === '1' || $value === true || $value === 1) {
            return 'y';
        } elseif ($value === 'n' || $value === '0' || $value === false || $value === 0) {
            return 'n';
        } elseif ($value === '' || $value === null) {
            return null;
        } else {
            throw new ProgrammingError(
                'Got invalid boolean: %s',
                var_export($value, 1)
            );
        }
    }

    protected function setDisabled($disabled)
    {
        return parent::reallySet('disabled', $this->normalizeBoolean($disabled));
    }

    public function isDisabled()
    {
        return $this->get('disabled') === 'y';
    }

    public function markForRemoval($remove = true)
    {
        $this->shouldBeRemoved = $remove;
        return $this;
    }

    public function shouldBeRemoved()
    {
        return $this->shouldBeRemoved;
    }

    public function shouldBeRenamed()
    {
        return $this->hasBeenLoadedFromDb()
            && $this->getOriginalProperty('object_name') !== $this->getObjectName();
    }

    /**
     * @return IcingaObjectGroups[]
     */
    public function groups()
    {
        $this->assertGroupsSupport();
        if ($this->groups === null) {
            if ($this->hasBeenLoadedFromDb()) {
                $this->groups = IcingaObjectGroups::loadForStoredObject($this);
            } else {
                $this->groups = new IcingaObjectGroups($this);
            }
        }

        return $this->groups;
    }

    /**
     * @return IcingaTimePeriodRanges
     */
    public function ranges()
    {
        $this->assertRangesSupport();
        if ($this->ranges === null) {
            /** @var IcingaTimePeriodRanges $class */
            $class = $this->getRangeClass();
            if ($this->hasBeenLoadedFromDb()) {
                $this->ranges = $class::loadForStoredObject($this);
            } else {
                $this->ranges = new $class($this);
            }
        }

        return $this->ranges;
    }

    protected function getRangeClass()
    {
        if ($this->rangeClass === null) {
            $this->rangeClass = get_class($this) . 'Ranges';
        }

        return $this->rangeClass;
    }

    /**
     * @return IcingaObjectImports
     */
    public function imports()
    {
        $this->assertImportsSupport();
        if ($this->imports === null) {
            if ($this->hasBeenLoadedFromDb()) {
                $this->imports = IcingaObjectImports::loadForStoredObject($this);
            } else {
                $this->imports = new IcingaObjectImports($this);
            }
        }

        return $this->imports;
    }

    public function gotImports()
    {
        return $this->imports !== null;
    }

    public function setImports($imports)
    {
        if (! is_array($imports) && $imports !== null) {
            $imports = array($imports);
        }

        try {
            $this->imports()->set($imports);
        } catch (NestingError $e) {
            $this->imports = new IcingaObjectImports($this);
            // Force modification, otherwise it won't be stored when empty
            $this->imports->setModified()->set($imports);
        }

        if ($this->imports()->hasBeenModified()) {
            $this->invalidateResolveCache();
        }
    }

    public function getImports()
    {
        return $this->listImportNames();
    }

    /**
     * @deprecated This should no longer be in use
     * @return IcingaTemplateResolver
     */
    public function templateResolver()
    {
        if ($this->templateResolver === null) {
            $this->templateResolver = new IcingaTemplateResolver($this);
        }

        return $this->templateResolver;
    }

    public function getResolvedProperty($key, $default = null)
    {
        if (array_key_exists($key, $this->unresolvedRelatedProperties)) {
            $this->resolveUnresolvedRelatedProperty($key);
            $this->invalidateResolveCache();
        }

        $properties = $this->getResolvedProperties();
        if (property_exists($properties, $key)) {
            return $properties->$key;
        }

        return $default;
    }

    public function getInheritedProperty($key, $default = null)
    {
        if (array_key_exists($key, $this->unresolvedRelatedProperties)) {
            $this->resolveUnresolvedRelatedProperty($key);
            $this->invalidateResolveCache();
        }

        $properties = $this->getInheritedProperties();
        if (property_exists($properties, $key)) {
            return $properties->$key;
        }

        return $default;
    }

    public function getInheritedVar($varname)
    {
        try {
            $vars = $this->getInheritedVars();
        } catch (NestingError $e) {
            return null;
        }

        if (property_exists($vars, $varname)) {
            return $vars->$varname;
        } else {
            return null;
        }
    }

    public function getResolvedVar($varname)
    {
        try {
            $vars = $this->getResolvedVars();
        } catch (NestingError $e) {
            return null;
        }

        if (property_exists($vars, $varname)) {
            return $vars->$varname;
        } else {
            return null;
        }
    }

    public function getOriginForVar($varname)
    {
        try {
            $origins = $this->getOriginsVars();
        } catch (NestingError $e) {
            return null;
        }

        if (property_exists($origins, $varname)) {
            return $origins->$varname;
        } else {
            return null;
        }
    }

    public function getResolvedProperties()
    {
        return $this->getResolved('Properties');
    }

    public function getInheritedProperties()
    {
        return $this->getInherited('Properties');
    }

    public function getOriginsProperties()
    {
        return $this->getOrigins('Properties');
    }

    public function resolveProperties()
    {
        return $this->resolve('Properties');
    }

    public function getResolvedVars()
    {
        return $this->getResolved('Vars');
    }

    public function getInheritedVars()
    {
        return $this->getInherited('Vars');
    }

    public function resolveVars()
    {
        return $this->resolve('Vars');
    }

    public function getOriginsVars()
    {
        return $this->getOrigins('Vars');
    }

    public function getVars()
    {
        $vars = (object) array();
        foreach ($this->vars() as $key => $var) {
            if ($var->hasBeenDeleted()) {
                continue;
            }

            $vars->$key = $var->getValue();
        }

        return $vars;
    }

    public function getGroups()
    {
        return $this->groups()->listGroupNames();
    }

    public function setGroups($groups)
    {
        $this->groups()->set($groups);
        return $this;
    }

    protected function getResolved($what)
    {
        $func = 'resolve' . $what;
        $res = $this->$func();
        return $res['_MERGED_'];
    }

    protected function getInherited($what)
    {
        $func = 'resolve' . $what;
        $res = $this->$func();
        return $res['_INHERITED_'];
    }

    protected function getOrigins($what)
    {
        $func = 'resolve' . $what;
        $res = $this->$func();
        return $res['_ORIGINS_'];
    }

    protected function hasResolveCached($what)
    {
        return array_key_exists($what, $this->resolveCache);
    }

    protected function & getResolveCached($what)
    {
        return $this->resolveCache[$what];
    }

    protected function storeResolvedCache($what, $vals)
    {
        $this->resolveCache[$what] = $vals;
    }

    public function invalidateResolveCache()
    {
        $this->resolveCache = array();
        return $this;
    }

    public function countDirectDescendants()
    {
        $db = $this->getDb();
        $table = $this->getTableName();
        $type = $this->getShortTableName();

        $query = $db->select()->from(
            array('oi' => $table . '_inheritance'),
            array('cnt' => 'COUNT(*)')
        )->where('oi.parent_' . $type . '_id = ?', (int) $this->get('id'));

        return $db->fetchOne($query);
    }

    protected function triggerLoopDetection()
    {
        // $this->templateResolver()->listResolvedParentIds();
    }

    public function getSingleResolvedProperty($key, $default = null)
    {
        if (array_key_exists($key, $this->unresolvedRelatedProperties)) {
            $this->resolveUnresolvedRelatedProperty($key);
            $this->invalidateResolveCache();
        }

        if ($my = $this->get($key)) {
            if ($my !== null) {
                return $my;
            }
        }

        /** @var IcingaObject $object */
        foreach (array_reverse($this->imports()->getObjects()) as $object) {
            $v = $object->getSingleResolvedProperty($key);
            if (null !== $v) {
                return $v;
            }
        }

        return $default;
    }

    protected function resolve($what)
    {
        if ($this->hasResolveCached($what)) {
            return $this->getResolveCached($what);
        }

        // Force exception
        if ($this->hasBeenLoadedFromDb()) {
            $this->triggerLoopDetection();
        }

        $vals = array();
        $vals['_MERGED_']    = (object) array();
        $vals['_INHERITED_'] = (object) array();
        $vals['_ORIGINS_']   = (object) array();
        // $objects = $this->imports()->getObjects();
        $objects = IcingaTemplateRepository::instanceByObject($this)
            ->getTemplatesIndexedByNameFor($this, true);

        $get          = 'get'         . $what;
        $getInherited = 'getInherited' . $what;
        $getOrigins   = 'getOrigins'  . $what;

        $blacklist = array('id', 'object_type', 'object_name', 'disabled');
        foreach ($objects as $name => $object) {
            $origins = $object->$getOrigins();

            foreach ($object->$getInherited() as $key => $value) {
                if (in_array($key, $blacklist)) {
                    continue;
                }

                if (! property_exists($origins, $key)) {
                    // TODO:  Introduced with group membership resolver or
                    //        choices - this should not be required. Check this!
                    continue;
                }

                // $vals[$name]->$key = $value;
                $vals['_MERGED_']->$key = $value;
                $vals['_INHERITED_']->$key = $value;
                $vals['_ORIGINS_']->$key = $origins->$key;
            }

            foreach ($object->$get() as $key => $value) {
                // TODO: skip if default value?
                if ($value === null) {
                    continue;
                }
                if (in_array($key, $blacklist)) {
                    continue;
                }
                $vals['_MERGED_']->$key = $value;
                $vals['_INHERITED_']->$key = $value;
                $vals['_ORIGINS_']->$key = $name;
            }
        }

        foreach ($this->$get() as $key => $value) {
            if ($value === null) {
                continue;
            }

            // $vals[$this->object_name]->$key = $value;
            $vals['_MERGED_']->$key = $value;
        }

        $this->storeResolvedCache($what, $vals);

        return $vals;
    }

    public function matches(Filter $filter)
    {
        // TODO: speed up by passing only desired properties (filter columns) to
        //       toPlainObject method
        /** @var FilterChain|FilterExpression $filter */
        return $filter->matches($this->toPlainObject());
    }

    protected function assertCustomVarsSupport()
    {
        if (! $this->supportsCustomVars()) {
            throw new ProgrammingError(
                'Objects of type "%s" have no custom vars',
                $this->getType()
            );
        }

        return $this;
    }

    protected function assertGroupsSupport()
    {
        if (! $this->supportsGroups()) {
            throw new ProgrammingError(
                'Objects of type "%s" have no groups',
                $this->getType()
            );
        }

        return $this;
    }

    protected function assertRangesSupport()
    {
        if (! $this->supportsRanges()) {
            throw new ProgrammingError(
                'Objects of type "%s" have no ranges',
                $this->getType()
            );
        }

        return $this;
    }

    protected function assertImportsSupport()
    {
        if (! $this->supportsImports()) {
            throw new ProgrammingError(
                'Objects of type "%s" have no imports',
                $this->getType()
            );
        }

        return $this;
    }

    /**
     * @return CustomVariables
     */
    public function vars()
    {
        $this->assertCustomVarsSupport();
        if ($this->vars === null) {
            if ($this->hasBeenLoadedFromDb()) {
                if (PrefetchCache::shouldBeUsed()) {
                    $this->vars = PrefetchCache::instance()->vars($this);
                } else {
                    $this->vars = CustomVariables::loadForStoredObject($this);
                }

                if ($this->getShortTableName() === 'host') {
                    $this->vars->setOverrideKeyName(
                        $this->getConnection()->settings()->override_services_varname
                    );
                }
            } else {
                $this->vars = new CustomVariables();
            }
        }

        return $this->vars;
    }

    public function getVarsTableName()
    {
        return $this->getTableName() . '_var';
    }

    public function getShortTableName()
    {
        // strlen('icinga_') = 7
        return substr($this->getTableName(), 7);
    }

    public function getVarsIdColumn()
    {
        return $this->getShortTableName() . '_id';
    }

    public function hasProperty($key)
    {
        if ($this->propertyIsRelatedSet($key)) {
            return true;
        }

        if ($this->propertyIsMultiRelation($key)) {
            return true;
        }

        return parent::hasProperty($key);
    }

    public function isObject()
    {
        return $this->hasProperty('object_type')
            && $this->get('object_type') === 'object';
    }

    public function isTemplate()
    {
        return $this->hasProperty('object_type')
            && $this->get('object_type') === 'template';
    }

    public function isExternal()
    {
        return $this->hasProperty('object_type')
            && $this->get('object_type') === 'external_object';
    }

    public function isApplyRule()
    {
        return $this->hasProperty('object_type')
            && $this->get('object_type') === 'apply';
    }

    protected function storeRelatedObjects()
    {
        $this
            ->storeCustomVars()
            ->storeGroups()
            ->storeMultiRelations()
            ->storeImports()
            ->storeRanges()
            ->storeRelatedSets()
            ->storeArguments();
    }

    protected function beforeStore()
    {
        $this->resolveUnresolvedRelatedProperties();
        if ($this->gotImports()) {
            $this->imports()->getObjects();
        }
    }

    public function onInsert()
    {
        DirectorActivityLog::logCreation($this, $this->connection);
        $this->storeRelatedObjects();
    }

    public function onUpdate()
    {
        DirectorActivityLog::logModification($this, $this->connection);
        $this->storeRelatedObjects();
    }

    public function onStore()
    {
        $this->notifyResolvers();
    }

    /**
     * @return self
     */
    protected function storeCustomVars()
    {
        if ($this->supportsCustomVars()) {
            $this->vars !== null && $this->vars()->storeToDb($this);
        }

        return $this;
    }

    /**
     * @return self
     */
    protected function storeGroups()
    {
        if ($this->supportsGroups()) {
            $this->groups !== null && $this->groups()->store();
        }

        return $this;
    }

    /**
     * @return self
     */
    protected function storeMultiRelations()
    {
        foreach ($this->loadedMultiRelations as $rel) {
            $rel->store();
        }

        return $this;
    }

    /**
     * @return self
     */
    protected function storeRanges()
    {
        if ($this->supportsRanges()) {
            $this->ranges !== null && $this->ranges()->store();
        }

        return $this;
    }

    /**
     * @return self
     */
    protected function storeArguments()
    {
        if ($this instanceof ObjectWithArguments) {
            $this->gotArguments() && $this->arguments()->store();
        }

        return $this;
    }

    protected function notifyResolvers()
    {
    }

    /**
     * @return self
     */
    protected function storeRelatedSets()
    {
        foreach ($this->loadedRelatedSets as $set) {
            if ($set->hasBeenModified()) {
                $set->store();
            }
        }

        return $this;
    }

    /**
     * @return self
     */
    protected function storeImports()
    {
        if ($this->supportsImports()) {
            $this->imports !== null && $this->imports()->store();
        }

        return $this;
    }

    public function beforeDelete()
    {
        $this->cachedPlainUnmodified = $this->getPlainUnmodifiedObject();
    }

    public function getCachedUnmodifiedObject()
    {
        return $this->cachedPlainUnmodified;
    }

    public function onDelete()
    {
        DirectorActivityLog::logRemoval($this, $this->connection);
    }

    public function toSingleIcingaConfig()
    {
        $config = new IcingaConfig($this->connection);
        $object = $this;
        if ($object->isExternal()) {
            $object->set('object_type', 'object');
            $wasExternal = true;
        } else {
            $wasExternal = false;
        }

        try {
            $object->renderToConfig($config);
        } catch (Exception $e) {
            $config->configFile(
                'failed-to-render'
            )->prepend(
                "/** Failed to render this object **/\n"
                . '/*  ' . $e->getMessage() . ' */'
            );
        }
        if ($wasExternal) {
            $object->set('object_type', 'external_object');
        }

        return $config;
    }

    public function isSupportedInLegacy()
    {
        return $this->supportedInLegacy;
    }

    public function renderToLegacyConfig(IcingaConfig $config)
    {
        if ($this->isExternal()) {
            return;
        }

        if (! $this->isSupportedInLegacy()) {
            $config->configFile(
                'director/ignored-objects',
                '.cfg'
            )->prepend(
                sprintf(
                    "# Not supported for legacy config: %s object_name=%s\n",
                    get_class($this),
                    $this->getObjectName()
                )
            );
            return;
        }

        $filename = $this->getRenderingFilename();

        $deploymentMode = $config->getDeploymentMode();
        if ($deploymentMode === 'active-passive') {
            if ($this->getSingleResolvedProperty('zone_id')
                && array_key_exists('enable_active_checks', $this->defaultProperties)
            ) {
                $passive = clone($this);
                $passive->enable_active_checks = false;

                $config->configFile(
                    'director/master/' . $filename,
                    '.cfg'
                )->addLegacyObject($passive);
            }
        } elseif ($deploymentMode === 'masterless') {
            // no additional config
        } else {
            throw new ProgrammingError('Unsupported deployment mode: %s' .$deploymentMode);
        }

        $config->configFile(
            'director/' . $this->getRenderingZone($config) . '/' . $filename,
            '.cfg'
        )->addLegacyObject($this);
    }

    public function renderToConfig(IcingaConfig $config)
    {
        if ($config->isLegacy()) {
            $this->renderToLegacyConfig($config);
            return;
        }

        if ($this->isExternal()) {
            return;
        }

        $config->configFile(
            'zones.d/' . $this->getRenderingZone($config) . '/' . $this->getRenderingFilename()
        )->addObject($this);
    }

    public function getRenderingFilename()
    {
        $type = $this->getShortTableName();

        if ($this->isTemplate()) {
            $filename = strtolower($type) . '_templates';
        } elseif ($this->isApplyRule()) {
            $filename = strtolower($type) . '_apply';
        } else {
            $filename = strtolower($type) . 's';
        }

        return $filename;
    }

    public function getRenderingZone(IcingaConfig $config = null)
    {
        if ($this->hasUnresolvedRelatedProperty('zone_id')) {
            return $this->get('zone');
        }

        if ($this->hasProperty('zone_id')) {
            if (! $this->supportsImports()) {
                if ($zoneId = $this->get('zone_id')) {
                    // Config has a lookup cache, is faster:
                    return $config->getZoneName($zoneId);
                }
            }

            try {
                if ($zoneId = $this->getSingleResolvedProperty('zone_id')) {
                    // Config has a lookup cache, is faster:
                    return $config->getZoneName($zoneId);
                }
            } catch (Exception $e) {
                return self::RESOLVE_ERROR;
            }
        }

        if ($this->prefersGlobalZone()) {
            return $this->connection->getDefaultGlobalZoneName();
        }

        return $this->connection->getMasterZoneName();
    }

    protected function prefersGlobalZone()
    {
        return $this->isTemplate() || $this->isApplyRule();
    }

    protected function renderImports()
    {
        if (! $this->supportsImports()) {
            return '';
        }

        $ret = '';
        foreach ($this->getImports() as $name) {
            $ret .= '    import ' . c::renderString($name) . "\n";
        }

        if ($ret !== '') {
            $ret .= "\n";
        }
        return $ret;


        // TODO: parent_host ORDERed by weigth...
        if ($this->supportsImports()) {
            return $this->imports()->toConfigString();
        } else {
            return '';
        }
    }

    protected function renderLegacyImports()
    {
        if ($this->supportsImports()) {
            return $this->imports()->toLegacyConfigString();
        } else {
            return '';
        }
    }

    protected function renderLegacyRelationProperty($propertyName, $id, $renderKey = null)
    {
        return $this->renderLegacyObjectProperty(
            $renderKey ?: $propertyName,
            c1::renderString($this->getRelatedObjectName($propertyName, $id))
        );
    }

    // Disabled is a virtual property
    protected function renderDisabled()
    {
        return '';
    }

    /**
     * @codingStandardsIgnoreStart
     */
    protected function renderLegacyHost_id()
    {
        return $this->renderLegacyRelationProperty(
            'host',
            $this->get('host_id'),
            'host_name'
        );
    }

    /**
     * Display Name only exists for host/service in Icinga 1
     *
     * Render it as alias for everything by default.
     *
     * Alias does not exist in Icinga 2 currently!
     *
     * @return string
     */
    protected function renderLegacyDisplay_Name()
    {
        return c1::renderKeyValue('alias', $this->display_name);
    }

    protected function renderLegacyTimeout()
    {
        return '';
    }

    protected function renderLegacyEnable_active_checks()
    {
        return $this->renderLegacyBooleanProperty(
            'enable_active_checks',
            'active_checks_enabled'
        );
    }

    protected function renderLegacyEnable_passive_checks()
    {
        return $this->renderLegacyBooleanProperty(
            'enable_passive_checks',
            'passive_checks_enabled'
        );
    }

    protected function renderLegacyEnable_event_handler()
    {
        return $this->renderLegacyBooleanProperty(
            'enable_active_checks',
            'event_handler_enabled'
        );
    }

    protected function renderLegacyEnable_notifications()
    {
        return $this->renderLegacyBooleanProperty(
            'enable_notifications',
            'notifications_enabled'
        );
    }

    protected function renderLegacyEnable_perfdata()
    {
        return $this->renderLegacyBooleanProperty(
            'enable_perfdata',
            'process_perf_data'
        );
    }

    protected function renderLegacyVolatile()
    {
        // @codingStandardsIgnoreEnd
        return $this->renderLegacyBooleanProperty(
            'volatile',
            'is_volatile'
        );
    }

    protected function renderLegacyBooleanProperty($property, $legacyKey)
    {
        return c1::renderKeyValue(
            $legacyKey,
            c1::renderBoolean($this->$property)
        );
    }

    protected function renderProperties()
    {
        $out = '';
        $blacklist = array_merge(
            $this->propertiesNotForRendering,
            $this->prioritizedProperties
        );

        foreach ($this->properties as $key => $value) {
            if (in_array($key, $blacklist)) {
                continue;
            }

            $out .= $this->renderObjectProperty($key, $value);
        }

        return $out;
    }

    protected function renderLegacyProperties()
    {
        $out = '';
        $blacklist = array_merge(
            $this->propertiesNotForRendering,
            array() /* $this->prioritizedProperties */
        );

        foreach ($this->properties as $key => $value) {
            if (in_array($key, $blacklist)) {
                continue;
            }

            $out .= $this->renderLegacyObjectProperty($key, $value);
        }

        return $out;
    }

    protected function renderPrioritizedProperties()
    {
        $out = '';

        foreach ($this->prioritizedProperties as $key) {
            $out .= $this->renderObjectProperty($key, $this->properties[$key]);
        }

        return $out;
    }

    protected function renderObjectProperty($key, $value)
    {
        if (substr($key, -3) === '_id') {
            $short = substr($key, 0, -3);
            if ($this->hasUnresolvedRelatedProperty($key)) {
                return c::renderKeyValue(
                    $short, // NOT
                    c::renderString($this->$short)
                );
            }
        }

        if ($value === null) {
            return '';
        }

        $method = 'render' . ucfirst($key);
        if (method_exists($this, $method)) {
            return $this->$method($value);
        }

        if ($this->propertyIsBoolean($key)) {
            if ($value === $this->defaultProperties[$key]) {
                return '';
            } else {
                return c::renderKeyValue(
                    $this->booleans[$key],
                    c::renderBoolean($value)
                );
            }
        }

        if ($this->propertyIsInterval($key)) {
            return c::renderKeyValue(
                $this->intervalProperties[$key],
                c::renderInterval($value)
            );
        }

        if (substr($key, -3) === '_id'
             && $this->hasRelation($relKey = substr($key, 0, -3))
        ) {
            return $this->renderRelationProperty($relKey, $value);
        }

        return c::renderKeyValue(
            $key,
            $this->isApplyRule() ?
                c::renderStringWithVariables($value) :
                c::renderString($value)
        );
    }

    protected function renderLegacyObjectProperty($key, $value)
    {
        if (substr($key, -3) === '_id') {
            $short = substr($key, 0, -3);
            if ($this->hasUnresolvedRelatedProperty($key)) {
                return c1::renderKeyValue(
                    $short, // NOT
                    c1::renderString($this->$short)
                );
            }
        }

        if ($value === null) {
            return '';
        }

        $method = 'renderLegacy' . ucfirst($key);
        if (method_exists($this, $method)) {
            return $this->$method($value);
        }

        $method = 'render' . ucfirst($key);
        if (method_exists($this, $method)) {
            return $this->$method($value);
        }

        if ($this->propertyIsBoolean($key)) {
            if ($value === $this->defaultProperties[$key]) {
                return '';
            } else {
                return c1::renderKeyValue(
                    $this->booleans[$key],
                    c1::renderBoolean($value)
                );
            }
        }

        if (substr($key, -3) === '_id'
             && $this->hasRelation($relKey = substr($key, 0, -3))
        ) {
            return $this->renderLegacyRelationProperty($relKey, $value);
        }

        return c1::renderKeyValue($key, c1::renderString($value));
    }

    protected function renderBooleanProperty($key)
    {
        return c::renderKeyValue($key, c::renderBoolean($this->$key));
    }

    protected function renderPropertyAsSeconds($key)
    {
        return c::renderKeyValue($key, c::renderInterval($this->$key));
    }

    protected function renderSuffix()
    {
        return "}\n\n";
    }

    protected function renderLegacySuffix()
    {
        return "}\n\n";
    }

    /**
     * @return string
     */
    protected function renderCustomVars()
    {
        if ($this->supportsCustomVars()) {
            return $this->vars()->toConfigString($this->isApplyRule());
        } else {
            return '';
        }
    }

    /**
     * @return string
     */
    protected function renderLegacyCustomVars()
    {
        if ($this->supportsCustomVars()) {
            return $this->vars()->toLegacyConfigString();
        } else {
            return '';
        }
    }

    /**
     * @return string
     */
    protected function renderGroups()
    {
        if ($this->supportsGroups()) {
            return $this->groups()->toConfigString();
        } else {
            return '';
        }
    }

    /**
     * @return string
     */
    protected function renderLegacyGroups()
    {
        if ($this->supportsGroups()) {
            return $this->groups()->toLegacyConfigString();
        } else {
            return '';
        }
    }

    /**
     * @return string
     */
    protected function renderMultiRelations()
    {
        $out = '';
        foreach ($this->loadAllMultiRelations() as $rel) {
            $out .= $rel->toConfigString();
        }

        return $out;
    }

    /**
     * @return string
     */
    protected function renderRanges()
    {
        if ($this->supportsRanges()) {
            return $this->ranges()->toConfigString();
        } else {
            return '';
        }
    }

    /**
     * @return string
     */
    protected function renderLegacyRanges()
    {
        if ($this->supportsRanges()) {
            return $this->ranges()->toLegacyConfigString();
        } else {
            return '';
        }
    }

    /**
     * @return string
     */
    protected function renderArguments()
    {
        return '';
    }

    protected function renderRelatedSets()
    {
        $config = '';
        foreach ($this->relatedSets as $property => $class) {
            $config .= $this->getRelatedSet($property)->renderAs($property);
        }
        return $config;
    }

    protected function renderRelationProperty($propertyName, $id, $renderKey = null)
    {
        return c::renderKeyValue(
            $renderKey ?: $propertyName,
            c::renderString($this->getRelatedObjectName($propertyName, $id))
        );
    }

    protected function renderCommandProperty($commandId, $propertyName = 'check_command')
    {
        return c::renderKeyValue(
            $propertyName,
            c::renderString($this->connection->getCommandName($commandId))
        );
    }

    /**
     * @param $value
     * @return string
     * @codingStandardsIgnoreStart
     */
    protected function renderLegacyCheck_command($value)
    {
        // @codingStandardsIgnoreEnd
        $args = array();
        foreach ($this->vars() as $k => $v) {
            if (substr($k, 0, 3) == 'ARG') {
                $args[] = $v->getValue();
            }
        }

        array_unshift($args, $value);
        return c1::renderKeyValue('check_command', join('!', $args));
    }

    /**
     * @param $value
     * @return string
     * @codingStandardsIgnoreStart
     */
    protected function renderLegacyEvent_command($value)
    {
        // @codingStandardsIgnoreEnd
        return c1::renderKeyValue('event_handler', $value);
    }

    /**
     * We do not render zone properties, objects are stored to zone dirs
     *
     * Avoid complaints for method names with underscore:
     * @codingStandardsIgnoreStart
     *
     * @return string
     */
    protected function renderZone_id()
    {
        // @codingStandardsIgnoreEnd
        return '';
    }

    protected function renderCustomExtensions()
    {
        return '';
    }

    protected function renderLegacyCustomExtensions()
    {
        $str = '';

        // Set notification settings for the object to suppress warnings
        if (array_key_exists('enable_notifications', $this->defaultProperties)
            && $this->isTemplate()
        ) {
            $str .= c1::renderKeyValue('notification_period', 'notification_none');
            $str .= c1::renderKeyValue('notification_interval', '0');
            $str .= c1::renderKeyValue('contact_groups', 'icingaadmins');
        }

        // force rendering of check_command when ARG1 is set
        if ($this->supportsCustomVars() && array_key_exists('check_command_id', $this->defaultProperties)) {
            if ($this->vars()->get('ARG1') !== null
                && $this->get('check_command') === null
            ) {
                $command = $this->getResolvedRelated('check_command');
                $str .= $this->renderLegacyCheck_command($command->getObjectName());
            }
        }

        return $str;
    }

    protected function renderObjectHeader()
    {
        return sprintf(
            "%s %s %s {\n",
            $this->getObjectTypeName(),
            $this->getType(),
            c::renderString($this->getObjectName())
        );
    }

    public function getLegacyObjectType()
    {
        return strtolower($this->getType());
    }

    protected function renderLegacyObjectHeader()
    {
        $type = $this->getLegacyObjectType();

        if ($this->isTemplate()) {
            $name = c1::renderKeyValue(
                $this->getLegacyObjectKeyName(),
                c1::renderString($this->getObjectName())
            );
        } else {
            $name = c1::renderKeyValue(
                $this->getLegacyObjectKeyName(),
                c1::renderString($this->getObjectName())
            );
        }

        $str = sprintf(
            "define %s {\n$name",
            $type,
            $name
        );

        if ($this->isTemplate()) {
            $str .= c1::renderKeyValue('register', '0');
        }

        return $str;
    }

    protected function getLegacyObjectKeyName()
    {
        if ($this->isTemplate()) {
            return 'name';
        } else {
            return $this->getLegacyObjectType() . '_name';
        }
    }

    /**
     * @codingStandardsIgnoreStart
     */
    public function renderAssign_Filter()
    {
        // @codingStandardsIgnoreEnd
        return '    ' . AssignRenderer::forFilter(
            Filter::fromQueryString($this->get('assign_filter'))
        )->renderAssign() . "\n";
    }

    public function toLegacyConfigString()
    {
        $str = implode(array(
            $this->renderLegacyObjectHeader(),
            $this->renderLegacyImports(),
            $this->renderLegacyProperties(),
            $this->renderLegacyRanges(),
            //$this->renderArguments(),
            //$this->renderRelatedSets(),
            $this->renderLegacyGroups(),
            //$this->renderMultiRelations(),
            $this->renderLegacyCustomExtensions(),
            $this->renderLegacyCustomVars(),
            $this->renderLegacySuffix()
        ));

        $str = $this->alignLegacyProperties($str);

        if ($this->isDisabled()) {
            return
                "# --- This object has been disabled ---\n"
                . preg_replace('~^~m', '# ', trim($str))
                . "\n\n";
        } else {
            return $str;
        }
    }

    protected function alignLegacyProperties($configString)
    {
        $lines = preg_split('/\n/', $configString);
        $len = 24;

        foreach ($lines as &$line) {
            if (preg_match('/^\s{4}([^\t]+)\t+(.+)$/', $line, $m)) {
                if ($len - strlen($m[1]) < 0) {
                    $fill = ' ';
                } else {
                    $fill = str_repeat(' ', $len - strlen($m[1]));
                }

                $line = '    ' . $m[1] . $fill . $m[2];
            }
        }

        return implode("\n", $lines);
    }

    public function toConfigString()
    {
        $str = implode(array(
            $this->renderObjectHeader(),
            $this->renderPrioritizedProperties(),
            $this->renderImports(),
            $this->renderProperties(),
            $this->renderRanges(),
            $this->renderArguments(),
            $this->renderRelatedSets(),
            $this->renderGroups(),
            $this->renderMultiRelations(),
            $this->renderCustomExtensions(),
            $this->renderCustomVars(),
            $this->renderSuffix()
        ));

        if ($this->isDisabled()) {
            return "/* --- This object has been disabled ---\n"
                . $str . "*/\n";
        } else {
            return $str;
        }
    }

    public function isGroup()
    {
        return substr($this->getType(), -5) === 'Group';
    }

    public function hasCheckCommand()
    {
        return false;
    }

    protected function getType()
    {
        if ($this->type === null) {
            $parts = explode('\\', get_class($this));
            // 6 = strlen('Icinga');
            $this->type = substr(end($parts), 6);
        }

        return $this->type;
    }

    protected function getObjectTypeName()
    {
        if ($this->isTemplate()) {
            return 'template';
        } elseif ($this->isApplyRule()) {
            return 'apply';
        } else {
            return 'object';
        }
    }

    public function getObjectName()
    {
        if ($this->hasProperty('object_name')) {
            return $this->get('object_name');
        } else {
            // TODO: replace with an exception once finished
            throw new ProgrammingError(
                'Trying to access "object_name" for an instance of "%s"',
                get_class($this)
            );
        }
    }

    public static function classByType($type)
    {
        // allow for icinga_host and host
        $type = lcfirst(preg_replace('/^icinga_/', '', $type));

        if (strpos($type, 'data') === false) {
            $prefix = 'Icinga';
        } else {
            $prefix = 'Director';
        }

        // TODO: Provide a more sophisticated solution
        if ($type === 'hostgroup') {
            $type = 'hostGroup';
        } elseif ($type === 'usergroup') {
            $type = 'userGroup';
        } elseif ($type === 'timeperiod') {
            $type = 'timePeriod';
        } elseif ($type === 'servicegroup') {
            $type = 'serviceGroup';
        } elseif ($type === 'service_set') {
            $type = 'serviceSet';
        } elseif ($type === 'apiuser') {
            $type = 'apiUser';
        } elseif ($type === 'host_template_choice') {
            $type = 'templateChoiceHost';
        } elseif ($type === 'service_template_choice') {
            $type = 'TemplateChoiceService';
        }

        return 'Icinga\\Module\\Director\\Objects\\' . $prefix . ucfirst($type);
    }

    /**
     * @param $type
     * @param array $properties
     * @param Db|null $db
     *
     * @return IcingaObject
     */
    public static function createByType($type, $properties = array(), Db $db = null)
    {
        /** @var IcingaObject $class */
        $class = self::classByType($type);
        return $class::create($properties, $db);
    }

    /**
     * @param $type
     * @param $id
     * @param Db $db
     *
     * @return IcingaObject
     */
    public static function loadByType($type, $id, Db $db)
    {
        /** @var IcingaObject $class */
        $class = self::classByType($type);
        return $class::load($id, $db);
    }

    /**
     * @param $type
     * @param $id
     * @param Db $db
     *
     * @return bool
     */
    public static function existsByType($type, $id, Db $db)
    {
        /** @var IcingaObject $class */
        $class = self::classByType($type);
        return $class::exists($id, $db);
    }

    public static function loadAllByType($type, Db $db, $query = null, $keyColumn = 'object_name')
    {
        /** @var DbObject $class */
        $class = self::classByType($type);

        if (is_array($class::create()->getKeyName())) {
            return $class::loadAll($db, $query);
        } else {
            if (PrefetchCache::shouldBeUsed() && $query === null && $keyColumn === 'object_name') {
                $result = array();
                foreach ($class::prefetchAll($db) as $row) {
                    $result[$row->object_name] = $row;
                }

                return $result;
            } else {
                return $class::loadAll($db, $query, $keyColumn);
            }
        }
    }

    /**
     * @param $type
     * @param Db $db
     * @return IcingaObject[]
     * @throws ProgrammingError
     */
    public static function loadAllExternalObjectsByType($type, Db $db)
    {
        /** @var IcingaObject $class */
        $class = self::classByType($type);
        $dummy = $class::create();

        if (is_array($dummy->getKeyName())) {
            throw new ProgrammingError(
                'There is no support for loading external objects of type "%s"',
                $type
            );
        } else {
            $query = $db->getDbAdapter()
                ->select()
                ->from($dummy->getTableName())
                ->where('object_type = ?', 'external_object');

            return $class::loadAll($db, $query, 'object_name');
        }
    }

    public static function fromJson($json, Db $connection = null)
    {
        return static::fromPlainObject(json_decode($json), $connection);
    }

    public static function fromPlainObject($plain, Db $connection = null)
    {
        return static::create((array) $plain, $connection);
    }

    public function replaceWith(IcingaObject $object, $preserve = null)
    {
        if ($preserve === null) {
            $this->setProperties((array) $object->toPlainObject());
        } else {
            $plain = (array) $object->toPlainObject();
            foreach ($preserve as $k) {
                $v = $this->$k;
                if ($v !== null) {
                    $plain[$k] = $v;
                }
            }

            $this->setProperties($plain);
        }
        return $this;
    }

    // TODO: with rules? What if I want to override vars? Drop in favour of vars.x?
    public function merge(IcingaObject $object, $replaceVars = false)
    {
        $object = clone($object);

        if ($object->supportsCustomVars()) {
            $vars = $object->getVars();
            $object->set('vars', []);
        }

        if ($object->supportsGroups()) {
            $groups = $object->getGroups();
            $object->set('groups', []);
        }

        if ($object->supportsImports()) {
            $imports = $object->listImportNames();
            $object->set('imports', []);
        }

        $plain = (array) $object->toPlainObject(false, false);
        unset($plain['vars']);
        unset($plain['groups']);
        unset($plain['imports']);
        foreach ($plain as $p => $v) {
            if ($v === null) {
                // We want default values, but no null values
                continue;
            }

            $this->set($p, $v);
        }

        if ($object->supportsCustomVars()) {
            $myVars = $this->vars();
            if ($replaceVars) {
                $this->set('vars', $vars);
            } else {
                /** @var CustomVariables $vars */
                foreach ($vars as $key => $var) {
                    $myVars->set($key, $var);
                }
            }
        }

        if ($object->supportsGroups()) {
            if (! empty($groups)) {
                $this->set('groups', $groups);
            }
        }

        if ($object->supportsImports()) {
            if (! empty($imports)) {
                $this->set('imports', $imports);
            }
        }

        return $this;
    }

    public function toPlainObject(
        $resolved = false,
        $skipDefaults = false,
        array $chosenProperties = null,
        $resolveIds = true
    ) {
        $props = array();

        if ($resolved) {
            $p = $this->getInheritedProperties();
            foreach ($this->properties as $k => $v) {
                if ($v === null && property_exists($p, $k)) {
                    continue;
                }
                $p->$k = $v;
            }
        } else {
            $p = $this->properties;
        }

        foreach ($p as $k => $v) {
            // Do not ship ids for IcingaObjects:
            if ($resolveIds) {
                if ($k === 'id' && $this->hasProperty('object_name')) {
                    continue;
                }

                if ('_id' === substr($k, -3)) {
                    $relKey = substr($k, 0, -3);

                    if ($this->hasRelation($relKey)) {
                        if ($this->hasUnresolvedRelatedProperty($k)) {
                            $v = $this->$relKey;
                        } elseif ($v !== null) {
                            $v = $this->getRelatedObjectName($relKey, $v);
                        }

                        $k = $relKey;
                    } else {
                        throw new ProgrammingError('No such relation: %s', $relKey);
                    }
                }
            }

            // TODO: Do not ship null properties based on flag?
            if (!$skipDefaults || $this->differsFromDefaultValue($k, $v)) {
                if ($k === 'disabled' || $this->propertyIsBoolean($k)) {
                    if ($v === 'y') {
                        $props[$k] = true;
                    } elseif ($v === 'n') {
                        $props[$k] = false;
                    } else {
                        $props[$k] = $v;
                    }
                } else {
                    $props[$k] = $v;
                }
            }
        }

        if ($this->supportsGroups()) {
            // TODO: resolve
            $props['groups'] = $this->groups()->listGroupNames();
        }

        foreach ($this->loadAllMultiRelations() as $key => $rel) {
            if (count($rel) || !$skipDefaults) {
                $props[$key] = $rel->listRelatedNames();
            }
        }

        if ($this instanceof ObjectWithArguments) {
            $props['arguments'] = $this->arguments()->toPlainObject(
                $resolved,
                $skipDefaults
            );
        }

        if ($this->supportsCustomVars()) {
            if ($resolved) {
                $props['vars'] = $this->getResolvedVars();
            } else {
                $props['vars'] = $this->getVars();
            }
        }

        if ($this->supportsImports()) {
            if ($resolved) {
                $props['imports'] = [];
            } else {
                $props['imports'] = $this->listImportNames();
            }
        }

        if ($this->supportsRanges()) {
            // TODO: resolve
            $props['ranges'] = $this->get('ranges');
        }

        if ($skipDefaults) {
            foreach (array('imports', 'ranges', 'arguments') as $key) {
                if (empty($props[$key])) {
                    unset($props[$key]);
                }
            }

            if (array_key_exists('vars', $props)) {
                if (count((array) $props['vars']) === 0) {
                    unset($props['vars']);
                }
            }
            if (empty($props['groups'])) {
                unset($props['groups']);
            }
        }

        foreach ($this->relatedSets() as $property => $set) {
            if ($resolved) {
                if ($this->supportsImports()) {
                    $set = clone($set);
                    foreach ($this->imports()->getObjects() as $parent) {
                        $set->inheritFrom($parent->getRelatedSet($property));
                    }
                }

                $values = $set->getResolvedValues();
                if (empty($values)) {
                    if (!$skipDefaults) {
                        $props[$property] = null;
                    }
                } else {
                    $props[$property] = $values;
                }
            } else {
                if ($set->isEmpty()) {
                    if (!$skipDefaults) {
                        $props[$property] = null;
                    }
                } else {
                    $props[$property] = $set->toPlainObject();
                }
            }
        }

        if ($chosenProperties !== null) {
            $chosen = [];
            foreach ($chosenProperties as $k) {
                if (array_key_exists($k, $props)) {
                    $chosen[$k] = $props[$k];
                }
            }

            $props = $chosen;
        }
        ksort($props);

        return (object) $props;
    }

    public function listImportNames()
    {
        if ($this->gotImports()) {
            return $this->imports()->listImportNames();
        } else {
            return $this->templateTree()->listParentNamesFor($this);
        }
    }

    public function listImportIds()
    {
        return $this->templateTree()->listParentIdsFor($this);
    }

    public function listAncestorIds()
    {
        return $this->templateTree()->listAncestorIdsFor($this);
    }

    protected function templateTree()
    {
        return $this->templates()->tree();
    }

    protected function templates()
    {
        return IcingaTemplateRepository::instanceByObject($this, $this->getConnection());
    }

    protected function differsFromDefaultValue($key, $value)
    {
        if (array_key_exists($key, $this->defaultProperties)) {
            return $value !== $this->defaultProperties[$key];
        } else {
            return $value !== null;
        }
    }

    public function getUrlParams()
    {
        $params = array();

        if ($this->isApplyRule()) {
            $params['id'] = $this->get('id');
        } else {
            $params = array('name' => $this->getObjectName());

            if ($this->hasProperty('host_id') && $this->get('host_id')) {
                $params['host'] = $this->get('host');
            }

            if ($this->hasProperty('service_id') && $this->get('service_id')) {
                $params['service'] = $this->get('service');
            }

            if ($this->hasProperty('service_set_id') && $this->get('service_set_id')) {
                $params['set'] = $this->get('service_set');
            }
        }

        return $params;
    }

    public function getOnDeleteUrl()
    {
        $plural= preg_replace('/cys$/', 'cies', strtolower($this->getShortTableName()) . 's');
        return 'director/' . $plural;
    }

    public function toJson(
        $resolved = false,
        $skipDefaults = false,
        array $chosenProperties = null
    ) {

        return json_encode($this->toPlainObject($resolved, $skipDefaults, $chosenProperties));
    }

    public function getPlainUnmodifiedObject()
    {
        $props = array();

        foreach ($this->getOriginalProperties() as $k => $v) {
            // Do not ship ids for IcingaObjects:
            if ($k === 'id' && $this->hasProperty('object_name')) {
                continue;
            }

            if ('_id' === substr($k, -3)) {
                $relKey = substr($k, 0, -3);

                if ($this->hasRelation($relKey)) {
                    if ($v !== null) {
                        $v = $this->getRelatedObjectName($relKey, $v);
                    }

                    $k = $relKey;
                }
            }

            if ($this->differsFromDefaultValue($k, $v)) {
                $props[$k] = $v;
            }
        }

        if ($this->supportsCustomVars()) {
            $props['vars'] = (object) array();
            foreach ($this->vars()->getOriginalVars() as $name => $var) {
                $props['vars']->$name = $var->getValue();
            }
        }
        if ($this->supportsGroups()) {
            $groups = $this->groups()->listOriginalGroupNames();
            if (! empty($groups)) {
                $props['groups'] = $groups;
            }
        }
        if ($this->supportsImports()) {
            $imports = $this->imports()->listOriginalImportNames();
            if (! empty($imports)) {
                $props['imports'] = $imports;
            }
        }

        if ($this instanceof ObjectWithArguments) {
            $args = $this->arguments()->toUnmodifiedPlainObject();
            if (! empty($args)) {
                $props['arguments'] = $args;
            }
        }

        if ($this->supportsRanges()) {
            $ranges = $this->ranges()->getOriginalValues();
            if (!empty($ranges)) {
                $props['ranges'] = $ranges;
            }
        }

        foreach ($this->relatedSets() as $property => $set) {
            if ($set->isEmpty()) {
                continue;
            }

            $props[$property] = $set->getPlainUnmodifiedObject();
        }

        foreach ($this->loadAllMultiRelations() as $key => $rel) {
            $old = $rel->listOriginalNames();
            if (! empty($old)) {
                $props[$key] = $old;
            }
        }

        return (object) $props;
    }

    public function __toString()
    {
        try {
            return $this->toConfigString();
        } catch (Exception $e) {
            trigger_error($e);
            $previousHandler = set_exception_handler(
                function () {
                }
            );
            restore_error_handler();
            if ($previousHandler !== null) {
                call_user_func($previousHandler, $e);
                die();
            } else {
                die($e->getMessage());
            }
        }
    }

    public function __destruct()
    {
        unset($this->resolveCache);
        unset($this->vars);
        unset($this->groups);
        unset($this->imports);
        unset($this->ranges);
        if ($this instanceof ObjectWithArguments) {
            $this->unsetArguments();
        }

        parent::__destruct();
    }
}
