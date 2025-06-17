<?php

namespace Icinga\Module\Director\Objects;

use Exception;
use Icinga\Data\Filter\Filter;
use Icinga\Data\Filter\FilterChain;
use Icinga\Data\Filter\FilterExpression;
use Icinga\Exception\NotFoundError;
use Icinga\Module\Director\CustomVariable\CustomVariables;
use Icinga\Module\Director\Data\Db\DbDataFormatter;
use Icinga\Module\Director\Data\Db\DbObjectTypeRegistry;
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
use LogicException;
use RuntimeException;

abstract class IcingaObject extends DbObject implements IcingaConfigRenderer
{
    public const RESOLVE_ERROR = '(unable to resolve)';
    public const ALL_NON_GLOBAL_ZONES = '(all non-global zones)';

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

    // Property suffixed with _id must exist
    protected $relations = [
        // property => PropertyClass
    ];

    protected $relatedSets = [
        // property => ExtensibleSetClass
    ];

    protected $multiRelations = [
        // property => IcingaObjectClass
    ];

    /** @var IcingaObjectMultiRelations[] */
    protected $loadedMultiRelations = [];

    /**
     * Allows to set properties pointing to related objects by name without
     * loading the related object.
     *
     * @var array
     */
    protected $unresolvedRelatedProperties = [];

    protected $loadedRelatedSets = [];

    // Will be rendered first, before imports
    protected $prioritizedProperties = [];

    protected $propertiesNotForRendering = [
        'id',
        'object_name',
        'object_type',
    ];

    /**
     * Array of interval property names
     *
     * Those will be automagically munged to integers (seconds) and rendered
     * as durations (e.g. 2m 10s). Array expects (propertyName => renderedKey)
     *
     * @var array
     */
    protected $intervalProperties = [];

    /** @var  Db */
    protected $connection;

    private $vars;

    /** @var IcingaObjectGroups */
    private $groups;

    private $imports;

    /** @var  IcingaTimePeriodRanges - TODO: generic ranges */
    private $ranges;

    private $shouldBeRemoved = false;

    private $resolveCache = [];

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
        }

        return false;
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

    /**
     * @param $property
     * @return ExtensibleSet
     */
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

    /**
     * @return ExtensibleSet[]
     */
    protected function relatedSets()
    {
        $sets = [];
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

    /**
     * @param $property
     * @return IcingaObject
     */
    public function getRelated($property)
    {
        return $this->getRelatedObject($property, $this->{$property . '_id'});
    }

    /**
     * @param $property
     * @param $id
     * @return string
     */
    public function getRelatedObjectName($property, $id)
    {
        return $this->getRelatedObject($property, $id)->getObjectName();
    }

    /**
     * @param $property
     * @param $id
     * @return IcingaObject
     */
    protected function getRelatedObject($property, $id)
    {
        /** @var IcingaObject $class */
        $class = $this->getRelationClass($property);
        try {
            $object = $class::loadWithAutoIncId($id, $this->connection);
        } catch (NotFoundError $e) {
            throw new RuntimeException($e->getMessage(), 0, $e);
        }

        return $object;
    }

    /**
     * @param $property
     * @return IcingaObject|null
     */
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
        $class = DbObjectTypeRegistry::classByType($type);
        /** @var static $dummy */
        $dummy = $class::create([], $db);
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
     * @throws LogicException
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

            if ($type === null) {
                throw new LogicException(
                    'Cannot set assign_filter unless object_type has been set'
                );
            }
            throw new LogicException(sprintf(
                'I can only assign for applied objects or objects with native'
                . ' support for assignments, got %s',
                $type
            ));
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

    public function getUnresolvedRelated($property)
    {
        if ($this->hasRelation($property)) {
            $property .= '_id';
            if (isset($this->unresolvedRelatedProperties[$property])) {
                return $this->unresolvedRelatedProperties[$property];
            }

            return null;
        }

        throw new RuntimeException(sprintf(
            '%s "%s" has no %s reference',
            $this->getShortTableName(),
            $this->getObjectName(),
            $property
        ));
    }

    /**
     * @param $name
     */
    protected function resolveUnresolvedRelatedProperty($name)
    {
        $short = substr($name, 0, -3);
        /** @var IcingaObject $class */
        $class = $this->getRelationClass($short);
        try {
            $object = $class::load(
                $this->unresolvedRelatedProperties[$name],
                $this->connection
            );
        } catch (NotFoundError $e) {
            // Hint: eventually a NotFoundError would be better
            throw new RuntimeException(sprintf(
                'Unable to load object (%s: %s) referenced from %s "%s", %s',
                $short,
                $this->unresolvedRelatedProperties[$name],
                $this->getShortTableName(),
                $this->getObjectName(),
                lcfirst($e->getMessage())
            ), $e->getCode(), $e);
        }

        $id = $object->get('id');
        // Happens when load() get's a branched object, created in the branch
        if ($id !== null) {
            $this->reallySet($name, $id);
            unset($this->unresolvedRelatedProperties[$name]);
        }
    }

    /**
     * @return bool
     */
    public function hasBeenModified()
    {
        if (parent::hasBeenModified()) {
            return true;
        }

        if ($this->hasUnresolvedRelatedProperties()) {
            $this->resolveUnresolvedRelatedProperties();

            // Duplicates above code, but this makes it faster:
            if (parent::hasBeenModified()) {
                return true;
            }
        }

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

        if (
            $this instanceof ObjectWithArguments
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

    protected function hasUnresolvedRelatedProperties()
    {
        return ! empty($this->unresolvedRelatedProperties);
    }

    protected function hasUnresolvedRelatedProperty($name)
    {
        return array_key_exists($name, $this->unresolvedRelatedProperties);
    }

    /**
     * @param $key
     * @return mixed
     */
    protected function getRelationId($key)
    {
        if ($this->hasUnresolvedRelatedProperty($key)) {
            $this->resolveUnresolvedRelatedProperty($key);
        }

        return parent::get($key);
    }

    /**
     * @param $key
     * @return string|null
     */
    protected function getRelatedProperty($key)
    {
        $idKey = $key . '_id';
        if ($this->hasUnresolvedRelatedProperty($idKey)) {
            return $this->unresolvedRelatedProperties[$idKey];
        }

        if ($id = $this->get($idKey)) {
            /** @var IcingaObject $class */
            $class = $this->getRelationClass($key);
            try {
                $object = $class::loadWithAutoIncId($id, $this->connection);
            } catch (NotFoundError $e) {
                throw new RuntimeException($e->getMessage(), 0, $e);
            }

            return $object->getObjectName();
        }

        return null;
    }

    /**
     * @param string $key
     * @return \Icinga\Module\Director\CustomVariable\CustomVariable|mixed|null
     */
    public function get($key)
    {
        if (substr($key, 0, 5) === 'vars.') {
            $var = $this->vars()->get(substr($key, 5));
            if ($var === null) {
                return $var;
            }

            return $var->getValue();
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
                $props = ['object_type' => $type] + $props;
            }
        }
        return parent::setProperties($props);
    }

    public function set($key, $value)
    {
        if ($key === 'vars') {
            $value = (array) $value;
            $unset = [];
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
        }

        if (substr($key, 0, 5) === 'vars.') {
            //TODO: allow for deep keys
            $this->vars()->set(substr($key, 5), $value);
            return $this;
        }

        if (
            $this instanceof ObjectWithArguments
            && substr($key, 0, 10) === 'arguments.'
        ) {
            $this->arguments()->set(substr($key, 10), $value);
            return $this;
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
        if ($value === null || strlen($value) === 0) {
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

    protected function setDisabled($disabled)
    {
        return $this->reallySet('disabled', DbDataFormatter::normalizeBoolean($disabled));
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
     * @return IcingaObjectGroups
     */
    public function groups()
    {
        $this->assertGroupsSupport();
        if ($this->groups === null) {
            if ($this->hasBeenLoadedFromDb() && $this->get('id')) {
                $this->groups = IcingaObjectGroups::loadForStoredObject($this);
            } else {
                $this->groups = new IcingaObjectGroups($this);
            }
        }

        return $this->groups;
    }

    public function hasModifiedGroups()
    {
        $this->assertGroupsSupport();
        if ($this->groups === null) {
            return false;
        }

        return $this->groups->hasBeenModified();
    }

    public function getAppliedGroups()
    {
        $this->assertGroupsSupport();
        if (! $this->hasBeenLoadedFromDb()) {
            // There are no stored related/resolved groups. We'll also not resolve
            // them here on demand.
            return [];
        }
        $id = $this->get('id');
        if ($id === null) {
            // Do not fail for branches. Should be handled otherwise
            // TODO: throw an Exception, once we are able to deal with this
            return [];
        }

        $type = strtolower($this->getType());
        $query = $this->db->select()->from(
            ['gr' => "icinga_{$type}group_{$type}_resolved"],
            ['g.object_name']
        )->join(
            ['g' => "icinga_{$type}group"],
            "g.id = gr.{$type}group_id",
            []
        )->joinLeft(
            ['go' => "icinga_{$type}group_{$type}"],
            "go.{$type}group_id = gr.{$type}group_id AND go.{$type}_id = " . (int) $id,
            []
        )->where(
            "gr.{$type}_id = ?",
            (int) $id
        )->where("go.{$type}_id IS NULL")->order('g.object_name');

        return $this->db->fetchCol($query);
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
            // can not use hasBeenLoadedFromDb() when in onStore()
            if ($this->getProperty('id') !== null) {
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
            $imports = [$imports];
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
        }

        return null;
    }

    public function getResolvedVar($varName)
    {
        try {
            $vars = $this->getResolvedVars();
        } catch (NestingError $e) {
            return null;
        }

        if (property_exists($vars, $varName)) {
            return $vars->$varName;
        }

        return null;
    }

    public function getOriginForVar($varName)
    {
        try {
            $origins = $this->getOriginsVars();
        } catch (NestingError $e) {
            return null;
        }

        if (property_exists($origins, $varName)) {
            return $origins->$varName;
        }

        return null;
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
        $vars = [];
        foreach ($this->vars() as $key => $var) {
            if ($var->hasBeenDeleted()) {
                continue;
            }

            $vars[$key] = $var->getValue();
        }
        ksort($vars);

        return (object) $vars;
    }

    /**
     * This is mostly for magic getters
     * @return array
     */
    public function getGroups()
    {
        return $this->groups()->listGroupNames();
    }

    /**
     * @return array
     * @throws NotFoundError
     */
    public function listInheritedGroupNames()
    {
        $parents = $this->imports()->getObjects();
        /** @var IcingaObject $parent */
        foreach (array_reverse($parents) as $parent) {
            $inherited = $parent->getGroups();
            if (! empty($inherited)) {
                return $inherited;
            }
        }

        return [];
    }

    public function setGroups($groups)
    {
        $this->groups()->set($groups);
        return $this;
    }

    /**
     * @return array
     * @throws NotFoundError
     */
    public function listResolvedGroupNames()
    {
        $groups = $this->groups()->listGroupNames();
        if (empty($groups)) {
            return $this->listInheritedGroupNames();
        }

        return $groups;
    }

    /**
     * @param $group
     * @return bool
     * @throws NotFoundError
     */
    public function hasGroup($group)
    {
        if ($group instanceof static) {
            $group = $group->getObjectName();
        }

        return in_array($group, $this->listResolvedGroupNames());
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
        $this->resolveCache = [];
        return $this;
    }

    public function countDirectDescendants()
    {
        $db = $this->getDb();
        $table = $this->getTableName();
        $type = $this->getShortTableName();

        $query = $db->select()->from(
            ['oi' => $table . '_inheritance'],
            ['cnt' => 'COUNT(*)']
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

        /** @var IcingaObject[] $imports */
        try {
            $imports = array_reverse($this->imports()->getObjects());
        } catch (NotFoundError $e) {
            throw new RuntimeException($e->getMessage(), 0, $e);
        }

        // Eventually trigger loop detection
        $this->listAncestorIds();

        foreach ($imports as $object) {
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

        $vals = [];
        $vals['_MERGED_']    = (object) [];
        $vals['_INHERITED_'] = (object) [];
        $vals['_ORIGINS_']   = (object) [];
        // $objects = $this->imports()->getObjects();
        $objects = IcingaTemplateRepository::instanceByObject($this)
            ->getTemplatesIndexedByNameFor($this, true);

        $get          = 'get'         . $what;
        $getInherited = 'getInherited' . $what;
        $getOrigins   = 'getOrigins'  . $what;

        $blacklist = ['id', 'uuid', 'object_type', 'object_name', 'disabled'];
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
            throw new LogicException(sprintf(
                'Objects of type "%s" have no custom vars',
                $this->getType()
            ));
        }

        return $this;
    }

    protected function assertGroupsSupport()
    {
        if (! $this->supportsGroups()) {
            throw new LogicException(sprintf(
                'Objects of type "%s" have no groups',
                $this->getType()
            ));
        }

        return $this;
    }

    protected function assertRangesSupport()
    {
        if (! $this->supportsRanges()) {
            throw new LogicException(sprintf(
                'Objects of type "%s" have no ranges',
                $this->getType()
            ));
        }

        return $this;
    }

    protected function assertImportsSupport()
    {
        if (! $this->supportsImports()) {
            throw new LogicException(sprintf(
                'Objects of type "%s" have no imports',
                $this->getType()
            ));
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
                    if ($this->get('id')) {
                        $this->vars = CustomVariables::loadForStoredObject($this);
                    } else {
                        $this->vars = new CustomVariables();
                    }
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

    /**
     * @return bool
     */
    public function hasInitializedVars()
    {
        $this->assertCustomVarsSupport();

        return $this->vars !== null;
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

    public function setBeingLoadedFromDb()
    {
        if ($this instanceof ObjectWithArguments && $this->gotArguments()) {
            $this->arguments()->setBeingLoadedFromDb();
        }
        if ($this->supportsImports() && $this->gotImports()) {
            $this->imports()->setBeingLoadedFromDb();
        }
        if ($this->supportsCustomVars() && $this->vars !== null) {
            $this->vars()->setBeingLoadedFromDb();
        }
        if ($this->supportsGroups() && $this->groups !== null) {
            $this->groups()->setBeingLoadedFromDb();
        }
        if ($this->supportsRanges() && $this->ranges !== null) {
            $this->ranges()->setBeingLoadedFromDb();
        }

        foreach ($this->loadedRelatedSets as $set) {
            $set->setBeingLoadedFromDb();
        }

        foreach ($this->loadedMultiRelations as $multiRelation) {
            $multiRelation->setBeingLoadedFromDb();
        }
        // This might trigger DB requests and 404's. We might want to defer this, but a call to
        // hasBeenModified triggers  anyway:
        $this->resolveUnresolvedRelatedProperties();

        parent::setBeingLoadedFromDb();
    }

    /**
     * @throws NotFoundError
     * @throws \Icinga\Module\Director\Exception\DuplicateKeyException
     * @throws \Zend_Db_Adapter_Exception
     */
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

    /**
     * @throws NotFoundError
     */
    protected function beforeStore()
    {
        $this->resolveUnresolvedRelatedProperties();
        if ($this->gotImports()) {
            $this->imports()->getObjects();
        }
    }

    /**
     * @throws NotFoundError
     * @throws \Icinga\Module\Director\Exception\DuplicateKeyException
     * @throws \Zend_Db_Adapter_Exception
     */
    public function onInsert()
    {
        DirectorActivityLog::logCreation($this, $this->connection);
        $this->storeRelatedObjects();
    }

    /**
     * @throws NotFoundError
     * @throws \Icinga\Module\Director\Exception\DuplicateKeyException
     * @throws \Zend_Db_Adapter_Exception
     */
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
     * @return $this
     * @throws \Icinga\Module\Director\Exception\DuplicateKeyException
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
     * @return $this
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
     * @return $this
     * @throws NotFoundError
     * @throws \Zend_Db_Adapter_Exception
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
            $message = $e->getMessage();
            $showTrace = false;
            if ($showTrace) {
                $message .= "\n" . $e->getTraceAsString();
            }
            $config->configFile(
                'failed-to-render'
            )->prepend(
                "/** Failed to render this object **/\n"
                . '/*  ' . $message . ' */'
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
            if (
                $this->getSingleResolvedProperty('zone_id')
                && array_key_exists('enable_active_checks', $this->defaultProperties)
            ) {
                $passive = clone($this);
                $passive->set('enable_active_checks', false);

                $config->configFile(
                    'director/master/' . $filename,
                    '.cfg'
                )->addLegacyObject($passive);
            }
        } elseif ($deploymentMode === 'masterless') {
            // no additional config
        } else {
            throw new LogicException(sprintf(
                'Unsupported deployment mode: %s',
                $deploymentMode
            ));
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

        foreach ($this->getRenderingZones($config) as $zone) {
            $config->configFile(
                'zones.d/' . $zone . '/' . $this->getRenderingFilename()
            )->addObject($this);
        }
    }

    protected function getRenderingZones(IcingaConfig $config): array
    {
        $zone = $this->getRenderingZone($config);
        if ($zone === self::ALL_NON_GLOBAL_ZONES) {
            return $config->listNonGlobalZones();
        }

        return [$zone];
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

    /**
     * @param $zoneId
     * @param IcingaConfig|null $config
     * @return string
     * @throws NotFoundError
     */
    protected function getNameForZoneId($zoneId, IcingaConfig $config = null)
    {
        // TODO: this is still ugly.
        if ($config === null) {
            return IcingaZone::loadWithAutoIncId(
                $zoneId,
                $this->getConnection()
            )->getObjectName();
        }

        // Config has a lookup cache, is faster:
        return $config->getZoneName($zoneId);
    }

    public function getRenderingZone(IcingaConfig $config = null)
    {
        if ($this->hasUnresolvedRelatedProperty('zone_id')) {
            return $this->get('zone');
        }

        if ($this->hasProperty('zone_id')) {
            try {
                if (! $this->supportsImports()) {
                    if ($zoneId = $this->get('zone_id')) {
                        return $this->getNameForZoneId($zoneId, $config);
                    }
                }

                if ($zoneId = $this->getSingleResolvedProperty('zone_id')) {
                    return $this->getNameForZoneId($zoneId, $config);
                }
            } catch (NestingError $e) {
                throw $e;
            } catch (Exception $e) {
                return self::RESOLVE_ERROR;
            }
        }

        return $this->getDefaultZone($config);
    }

    protected function getDefaultZone(IcingaConfig $config = null)
    {
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
    }

    protected function renderLegacyImports()
    {
        if ($this->supportsImports()) {
            return $this->imports()->toLegacyConfigString();
        }

        return '';
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
    protected function renderLegacyHost_id($value)
    {
        if (is_array($value)) {
            return c1::renderKeyValue('host_name', c1::renderArray($value));
        }

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
            c1::renderBoolean($this->get($property))
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
            [] /* $this->prioritizedProperties */
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
            }

            return c::renderKeyValue(
                $this->booleans[$key],
                c::renderBoolean($value)
            );
        }

        if ($this->propertyIsInterval($key)) {
            return c::renderKeyValue(
                $this->intervalProperties[$key],
                c::renderInterval($value)
            );
        }

        if (
            substr($key, -3) === '_id'
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
            }

            return c1::renderKeyValue(
                $this->booleans[$key],
                c1::renderBoolean($value)
            );
        }

        if ($this->propertyIsInterval($key)) {
            return c1::renderKeyValue(
                $this->intervalProperties[$key],
                c1::renderInterval($value)
            );
        }

        if (
            substr($key, -3) === '_id'
             && $this->hasRelation($relKey = substr($key, 0, -3))
        ) {
            return $this->renderLegacyRelationProperty($relKey, $value);
        }

        return c1::renderKeyValue($key, c1::renderString($value));
    }

    protected function renderBooleanProperty($key)
    {
        return c::renderKeyValue($key, c::renderBoolean($this->get($key)));
    }

    protected function renderPropertyAsSeconds($key)
    {
        return c::renderKeyValue($key, c::renderInterval($this->get($key)));
    }

    protected function renderSuffix()
    {
        $prefix = '';
        if ($this->rendersConditionalTemplate()) {
            $prefix = '} ';
        }

        return "$prefix}\n\n";
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
        }

        return '';
    }

    /**
     * @return string
     */
    protected function renderLegacyCustomVars()
    {
        if ($this->supportsCustomVars()) {
            return $this->vars()->toLegacyConfigString();
        }

        return '';
    }

    public function renderUuid()
    {
        return '';
    }

    /**
     * @return string
     */
    protected function renderGroups()
    {
        if ($this->supportsGroups()) {
            return $this->groups()->toConfigString();
        }

        return '';
    }

    /**
     * @return string
     */
    protected function renderLegacyGroups()
    {
        if ($this->supportsGroups() && $this->hasBeenLoadedFromDb()) {
            $applied = [];
            if ($this instanceof IcingaHost) {
                $applied = $this->getAppliedGroups();
            }
            return $this->groups()->toLegacyConfigString($applied);
        }

        return '';
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
    protected function renderLegacyMultiRelations()
    {
        $out = '';
        foreach ($this->loadAllMultiRelations() as $rel) {
            $out .= $rel->toLegacyConfigString();
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
        }

        return '';
    }

    /**
     * @return string
     */
    protected function renderLegacyRanges()
    {
        if ($this->supportsRanges()) {
            return $this->ranges()->toLegacyConfigString();
        }

        return '';
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
        $args = [];
        foreach ($this->vars() as $k => $v) {
            if (substr($k, 0, 3) === 'ARG') {
                $args[] = $v->getValue();
            }
        }
        array_unshift($args, $value);

        return c1::renderKeyValue('check_command', implode('!', $args));
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
        if (
            array_key_exists('enable_notifications', $this->defaultProperties)
            && $this->isTemplate()
        ) {
            $str .= c1::renderKeyValue('notification_period', 'notification_none');
            $str .= c1::renderKeyValue('notification_interval', '0');
            $str .= c1::renderKeyValue('contact_groups', 'icingaadmins');
        }

        // force rendering of check_command when ARG1 is set
        if ($this->supportsCustomVars() && array_key_exists('check_command_id', $this->defaultProperties)) {
            if (
                $this->get('check_command') === null
                && $this->vars()->get('ARG1') !== null
            ) {
                $command = $this->getResolvedRelated('check_command');
                $str .= $this->renderLegacyCheck_command($command->getObjectName());
            }
        }

        return $str;
    }

    protected function renderObjectHeader()
    {
        $prefix = '';
        $renderedName = c::renderString($this->getObjectName());
        if ($this->rendersConditionalTemplate()) {
            $prefix = sprintf('if (! get_template(%s, %s)) { ', $this->getType(), $renderedName);
        }
        return sprintf(
            "%s%s %s %s {\n",
            $prefix,
            $this->getObjectTypeName(),
            $this->getType(),
            $renderedName
        );
    }

    protected function rendersConditionalTemplate(): bool
    {
        return false;
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

        $str = "define $type {\n$name";
        if ($this->isTemplate()) {
            $str .= c1::renderKeyValue('register', '0');
        }

        return $str;
    }

    protected function getLegacyObjectKeyName()
    {
        if ($this->isTemplate()) {
            return 'name';
        }

        return $this->getLegacyObjectType() . '_name';
    }

    /**
     * @codingStandardsIgnoreStart
     */
    public function renderAssign_Filter()
    {
        return '    ' . AssignRenderer::forFilter(
            Filter::fromQueryString($this->get('assign_filter'))
        )->renderAssign() . "\n";
    }

    public function renderLegacyAssign_Filter()
    {
        // @codingStandardsIgnoreEnd
        if ($this instanceof IcingaHostGroup) {
            $c = "    # resolved memberships are set via the individual object\n";
        } elseif ($this instanceof IcingaService) {
            $c = "    # resolved objects are listed here\n";
        } else {
            $c = "    # assign is not supported for " . $this->type . "\n";
        }
        $c .= '    #' . AssignRenderer::forFilter(
            Filter::fromQueryString($this->get('assign_filter'))
        )->renderAssign() . "\n";
        return $c;
    }

    public function toLegacyConfigString()
    {
        $str = implode([
            $this->renderLegacyObjectHeader(),
            $this->renderLegacyImports(),
            $this->renderLegacyProperties(),
            //$this->renderArguments(),
            //$this->renderRelatedSets(),
            $this->renderLegacyGroups(),
            $this->renderLegacyMultiRelations(),
            $this->renderLegacyRanges(),
            $this->renderLegacyCustomExtensions(),
            $this->renderLegacyCustomVars(),
            $this->renderLegacySuffix()
        ]);

        $str = $this->alignLegacyProperties($str);

        if ($this->isDisabled()) {
            return
                "# --- This object has been disabled ---\n"
                . preg_replace('~^~m', '# ', trim($str))
                . "\n\n";
        }

        return $str;
    }

    protected function alignLegacyProperties($configString)
    {
        $lines = explode("\n", $configString);
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
        $str = implode([
            $this->renderObjectHeader(),
            $this->renderPrioritizedProperties(),
            $this->renderImports(),
            $this->renderProperties(),
            $this->renderArguments(),
            $this->renderRelatedSets(),
            $this->renderGroups(),
            $this->renderMultiRelations(),
            $this->renderRanges(),
            $this->renderCustomExtensions(),
            $this->renderCustomVars(),
            $this->renderSuffix()
        ]);

        if ($this->isDisabled()) {
            return "/* --- This object has been disabled ---\n"
                // Do not allow strings to break our comment
                . str_replace('*/', "* /", $str) . "*/\n";
        }

        return $str;
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
        }
        if ($this->isApplyRule()) {
            return 'apply';
        }

        return 'object';
    }

    public function getObjectName()
    {
        $property = static::getKeyColumnName();
        if ($this->hasProperty($property)) {
            return $this->get($property);
        }

        throw new LogicException(sprintf(
            'Trying to access "%s" for an instance of "%s"',
            $property,
            get_class($this)
        ));
    }

    /**
     * @deprecated use DbObjectTypeRegistry::classByType()
     * @param $type
     * @return string
     */
    public static function classByType($type)
    {
        return DbObjectTypeRegistry::classByType($type);
    }

    /**
     * @param $type
     * @param array $properties
     * @param Db|null $db
     *
     * @return IcingaObject
     */
    public static function createByType($type, $properties = [], Db $db = null)
    {
        /** @var IcingaObject $class */
        $class = DbObjectTypeRegistry::classByType($type);
        return $class::create($properties, $db);
    }

    /**
     * @param $type
     * @param $id
     * @param Db $db
     *
     * @return IcingaObject
     * @throws NotFoundError
     */
    public static function loadByType($type, $id, Db $db)
    {
        /** @var IcingaObject $class */
        $class = DbObjectTypeRegistry::classByType($type);
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
        $class = DbObjectTypeRegistry::classByType($type);
        return $class::exists($id, $db);
    }

    public static function getKeyColumnName()
    {
        return 'object_name';
    }

    public static function loadAllByType($type, Db $db, $query = null, $keyColumn = null)
    {
        /** @var DbObject $class */
        $class = DbObjectTypeRegistry::classByType($type);

        if ($keyColumn === null && is_array($class::create()->getKeyName())) {
            return $class::loadAll($db, $query);
        }

        if ($keyColumn === null) {
            if (method_exists($class, 'getKeyColumnName')) {
                $keyColumn = $class::getKeyColumnName();
            }
        }

        if (
            PrefetchCache::shouldBeUsed()
            && $query === null
            && $keyColumn === static::getKeyColumnName()
        ) {
            $result = [];
            foreach ($class::prefetchAll($db) as $row) {
                $result[$row->$keyColumn] = $row;
            }

            return $result;
        }

        return $class::loadAll($db, $query, $keyColumn);
    }

    /**
     * @param $type
     * @param Db $db
     * @return IcingaObject[]
     */
    public static function loadAllExternalObjectsByType($type, Db $db)
    {
        /** @var IcingaObject $class */
        $class = DbObjectTypeRegistry::classByType($type);
        $dummy = $class::create();

        if (is_array($dummy->getKeyName())) {
            throw new LogicException(sprintf(
                'There is no support for loading external objects of type "%s"',
                $type
            ));
        }

        $query = $db->getDbAdapter()
            ->select()
            ->from($dummy->getTableName())
            ->where('object_type = ?', 'external_object');

        return $class::loadAll($db, $query, 'object_name');
    }

    public static function fromJson($json, Db $connection = null)
    {
        return static::fromPlainObject(json_decode($json), $connection);
    }

    public static function fromPlainObject($plain, Db $connection = null)
    {
        return static::create((array) $plain, $connection);
    }

    /**
     * @param IcingaObject $object
     * @param null $preserve
     * @return $this
     * @throws NotFoundError
     */
    public function replaceWith(IcingaObject $object, $preserve = [])
    {
        return $this->replaceWithProperties($object->toPlainObject(), $preserve);
    }

    /**
     * @param array|object $properties
     * @param array $preserve
     * @return $this
     * @throws NotFoundError
     */
    public function replaceWithProperties($properties, $preserve = [])
    {
        $properties = (array) $properties;
        foreach ($preserve as $k) {
            $v = $this->get($k);
            if ($v !== null) {
                $properties[$k] = $v;
            }
        }
        $this->setProperties($properties);

        return $this;
    }

    /**
     * TODO: with rules? What if I want to override vars? Drop in favour of vars.x?
     *
     * @param IcingaObject $object
     * @param bool $replaceVars
     * @return $this
     * @throws NotFoundError
     */
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
        unset($plain['vars'], $plain['groups'], $plain['imports']);
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

    /**
     * @param bool $resolved
     * @param bool $skipDefaults
     * @param array|null $chosenProperties
     * @param bool $resolveIds
     * @param bool $keepId
     * @return object
     * @throws NotFoundError
     */
    public function toPlainObject(
        $resolved = false,
        $skipDefaults = false,
        array $chosenProperties = null,
        $resolveIds = true,
        $keepId = false
    ) {
        $props = [];

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
            if ($k === $this->getUuidColumn()) {
                continue;
            }
            if ($resolveIds) {
                if ($k === 'id' && $keepId === false && $this->hasProperty('object_name')) {
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
                        throw new LogicException(sprintf(
                            'No such relation: %s',
                            $relKey
                        ));
                    }
                }
            }
            if ($this->propertyIsInterval($k) && is_string($v) && ctype_digit($v)) {
                $v = (int) $v;
            }

            // TODO: Do not ship null properties based on flag?
            if (!$skipDefaults || $this->differsFromDefaultValue($k, $v)) {
                if ($k === 'disabled' || $this->propertyIsBoolean($k)) {
                    $props[$k] = DbDataFormatter::booleanForDbValue($v);
                } else {
                    $props[$k] = $v;
                }
            }
        }

        if ($this->supportsGroups()) {
            // TODO: resolve
            $groups = $this->groups()->listGroupNames();
            if ($resolved && empty($groups)) {
                $groups = $this->listInheritedGroupNames();
            }

            $props['groups'] = $groups;
        }

        foreach ($this->loadAllMultiRelations() as $key => $rel) {
            if (count($rel) || !$skipDefaults) {
                $props[$key] = $rel->listRelatedNames();
            }
        }

        if ($this instanceof ObjectWithArguments) {
            $props['arguments'] = $this->arguments()->toPlainObject(
                false,
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
            foreach (['imports', 'ranges', 'arguments'] as $key) {
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
        }

        return $this->templateTree()->listParentNamesFor($this);
    }

    public function listFlatResolvedImportNames()
    {
        return $this->templateTree()->getAncestorsFor($this);
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
        }

        return $value !== null;
    }

    protected function mapHostsToZones($names)
    {
        $map = [];

        foreach ($names as $hostname) {
            /** @var IcingaHost $host */
            $host = IcingaHost::load($hostname, $this->connection);

            $zone = $host->getRenderingZone();
            if (! array_key_exists($zone, $map)) {
                $map[$zone] = [];
            }

            $map[$zone][] = $hostname;
        }

        ksort($map);

        return $map;
    }

    public function getUrlParams()
    {
        $params = [];
        if ($column = $this->getUuidColumn()) {
            return [$column => $this->getUniqueId()->toString()];
        }

        if ($this->isApplyRule() && ! $this instanceof IcingaScheduledDowntime) {
            $params['id'] = $this->get('id');
        } else {
            $params = ['name' => $this->getObjectName()];

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
        $plural = preg_replace('/cys$/', 'cies', strtolower($this->getShortTableName()) . 's');
        return 'director/' . $plural;
    }

    /**
     * @param bool $resolved
     * @param bool $skipDefaults
     * @param array|null $chosenProperties
     * @return string
     * @throws NotFoundError
     */
    public function toJson(
        $resolved = false,
        $skipDefaults = false,
        array $chosenProperties = null
    ) {

        return json_encode($this->toPlainObject($resolved, $skipDefaults, $chosenProperties));
    }

    public function getPlainUnmodifiedObject()
    {
        $props = [];

        foreach ($this->getOriginalProperties() as $k => $v) {
            // Do not ship ids for IcingaObjects:
            if ($k === 'id' && $this->hasProperty('object_name')) {
                continue;
            }
            if ($k === $this->getUuidColumn()) {
                continue;
            }
            if ($k === 'disabled' && $v === null) {
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
                if ($k === 'disabled' || $this->propertyIsBoolean($k)) {
                    $props[$k] = DbDataFormatter::booleanForDbValue($v);
                } else {
                    $props[$k] = $v;
                }
            }
        }

        if ($this->supportsCustomVars()) {
            $originalVars = $this->vars()->getOriginalVars();
            if (! empty($originalVars)) {
                $props['vars'] = (object) [];
                foreach ($originalVars as $name => $var) {
                    $props['vars']->$name = $var->getValue();
                }
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
            }

            die($e->getMessage());
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
