<?php

namespace Icinga\Module\Director\Objects;

use Icinga\Module\Director\CustomVariable\CustomVariables;
use Icinga\Module\Director\Data\Db\DbObject;
use Icinga\Module\Director\Db\Cache\PrefetchCache;
use Icinga\Module\Director\Db;
use Icinga\Module\Director\Exception\NestingError;
use Icinga\Module\Director\IcingaConfig\IcingaConfig;
use Icinga\Module\Director\IcingaConfig\IcingaConfigRenderer;
use Icinga\Module\Director\IcingaConfig\IcingaConfigHelper as c;
use Icinga\Module\Director\IcingaConfig\IcingaLegacyConfigHelper as c1;
use Icinga\Data\Filter\Filter;
use Icinga\Exception\ConfigurationError;
use Icinga\Exception\ProgrammingError;
use Exception;

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

    /** @var bool Whether this object supports (command) Arguments */
    protected $supportsArguments = false;

    /** @var bool Whether inheritance via "imports" property is supported */
    protected $supportsImports = false;

    /** @var bool Allows controlled custom var access through Fields */
    protected $supportsFields = false;

    /** @var bool Whether this object can be rendered as 'apply Object' */
    protected $supportsApplyRules = false;

    /** @var bool Whether Sets of object can be defined */
    protected $supportsSets = false;

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

    private $vars;

    private $groups;

    private $imports;

    private $ranges;

    private $arguments;

    private $assignments;

    private $shouldBeRemoved = false;

    private $resolveCache = array();

    private $cachedPlainUnmodified;

    private $templateResolver;

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

    protected function getRelatedObjectName($property, $id)
    {
        return $this->getRelatedObject($property, $id)->object_name;
    }

    protected function getRelatedObject($property, $id)
    {
        $class = $this->getRelationClass($property);
        return $class::loadWithAutoIncId($id, $this->connection);
    }

    public function getResolvedRelated($property)
    {
        $id = $this->getResolvedProperty($property . '_id');

        if ($id) {
            return $this->getRelatedObject($property, $id);
        }

        return null;
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
        return $this->supportsArguments;
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
        $class = $this->getRelationClass($short);
        $object = $class::load(
            $this->unresolvedRelatedProperties[$name],
            $this->connection
        );

        $this->reallySet($name, $object->id);
        unset($this->unresolvedRelatedProperties[$name]);
    }

    public function hasBeenModified()
    {
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

        if ($this->supportsArguments() && $this->arguments !== null && $this->arguments()->hasBeenModified()) {
            return true;
        }

        if ($this->supportsAssignments() && $this->assignments !== null && $this->assignments()->hasBeenModified()) {
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

        return parent::hasBeenModified();
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
            $class = $this->getRelationClass($key);
            $object = $class::loadWithAutoIncId($id, $this->connection);
            return $object->object_name;
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
        } elseif (substr($key, 0, 10) === 'arguments.') {
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

    protected function setArguments($value)
    {
        $this->arguments()->setArguments($value);
        return $this;
    }

    protected function getArguments()
    {
        return $this->arguments()->toPlainObject();
    }

    protected function setAssignments($value)
    {
        $this->assignments()->setValues($value);
        return $this;
    }

    public function assignments()
    {
        if ($this->assignments === null) {
            $this->assignments = new IcingaObjectAssignments($this);
        }

        return $this->assignments;
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
        return $this->disabled === 'y';
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

    public function ranges()
    {
        $this->assertRangesSupport();
        if ($this->ranges === null) {
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

    public function arguments()
    {
        $this->assertArgumentsSupport();
        if ($this->arguments === null) {
            if ($this->hasBeenLoadedFromDb()) {
                $this->arguments = IcingaArguments::loadForStoredObject($this);
            } else {
                $this->arguments = new IcingaArguments($this);
            }
        }

        return $this->arguments;
    }

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
        return $this->imports()->listImportNames();
    }

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

    public function getResolvedFields()
    {
        return $this->getResolved('Fields');
    }

    public function getInheritedFields()
    {
        return $this->getInherited('Fields');
    }

    public function getOriginsFields()
    {
        return $this->getOrigins('Fields');
    }

    public function resolveFields()
    {
        return $this->resolve('Fields');
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
        if ($this->templateResolver) {
            $this->templateResolver()->clearCache();
        }

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
        )->where('oi.parent_' . $type . '_id = ?', (int) $this->id);

        return $db->fetchOne($query);
    }

    protected function triggerLoopDetection()
    {
        $this->templateResolver()->listResolvedParentIds();
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
        $objects = $this->imports()->getObjects();

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

    protected function assertArgumentsSupport()
    {
        if (! $this->supportsArguments()) {
            throw new ProgrammingError(
                'Objects of type "%s" have no arguments',
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

    public function getFields()
    {
        $fields = (object) array();

        if (! $this->supportsFields()) {
            return $fields;
        }

        $db = $this->getDb();

        $query = $db->select()->from(
            array('df' => 'director_datafield'),
            array(
                'datafield_id' => 'f.datafield_id',
                'is_required'  => 'f.is_required',
                'varname'      => 'df.varname',
                'description'  => 'df.description',
                'datatype'     => 'df.datatype',
                'format'       => 'df.format',
            )
        )->join(
            array('f' => $this->getTableName() . '_field'),
            'df.id = f.datafield_id',
            array()
        )->where('f.' . $this->getShortTableName() . '_id = ?', (int) $this->id)
         ->order('df.caption ASC');

        $res = $db->fetchAll($query);

        foreach ($res as $r) {
            $fields->{$r->varname} = $r;
        }

        return $fields;
    }

    protected function getAssignments()
    {
        return $this->assignments()->getValues();
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
            && $this->object_type === 'object';
    }

    public function isTemplate()
    {
        return $this->hasProperty('object_type')
            && $this->object_type === 'template';
    }

    public function isExternal()
    {
        return $this->hasProperty('object_type')
            && $this->object_type === 'external_object';
    }

    public function isApplyRule()
    {
        return $this->hasProperty('object_type')
            && $this->object_type === 'apply';
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
            ->storeArguments()
            ->storeAssignments();
    }

    protected function beforeStore()
    {
        $this->resolveUnresolvedRelatedProperties();
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

    protected function storeCustomVars()
    {
        if ($this->supportsCustomVars()) {
            $this->vars !== null && $this->vars()->storeToDb($this);
        }

        return $this;
    }

    protected function storeGroups()
    {
        if ($this->supportsGroups()) {
            $this->groups !== null && $this->groups()->store();
        }

        return $this;
    }

    protected function storeMultiRelations()
    {
        foreach ($this->loadedMultiRelations as $rel) {
            $rel->store();
        }

        return $this;
    }

    protected function storeRanges()
    {
        if ($this->supportsRanges()) {
            $this->ranges !== null && $this->ranges()->store();
        }

        return $this;
    }

    protected function storeArguments()
    {
        if ($this->supportsArguments()) {
            $this->arguments !== null && $this->arguments()->store();
        }

        return $this;
    }

    protected function storeAssignments()
    {
        if ($this->supportsAssignments()) {
            $this->assignments !== null && $this->assignments()->store();
        }

        return $this;
    }

    protected function storeRelatedSets()
    {
        foreach ($this->loadedRelatedSets as $set) {
            if ($set->hasBeenModified()) {
                $set->store();
            }
        }

        return $this;
    }

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
            $object = clone($object);
            $object->object_type = 'object';
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

        return $config;
    }


    public function renderToLegacyConfig(IcingaConfig $config)
    {
        if ($this->isExternal()) {
            return;
        }

        $filename = $this->getRenderingFilename();

        if ($config->isLegacy()) {

            if ($this->getResolvedProperty('zone_id')) {

                $a = clone($this);
                $a->enable_active_checks = true;

                $b = clone($this);
                $a->enable_active_checks = false;

                $config->configFile(
                    'director/master/' . $filename,
                    '.cfg'
                )->addLegacyObject($a);

                $config->configFile(
                    'director/' . $this->getRenderingZone($config) . '/' . $filename,
                    '.cfg'
                )->addLegacyObject($b);

            } else {
                $config->configFile(
                    'director/' . $this->getRenderingZone($config) . '/' . $filename,
                    '.cfg'
                )->addLegacyObject($this);
            }

        } else {
            $config->configFile(
                'director/' . $this->getRenderingZone($config) . '/' . $filename
            )->addObject($this);
        }
    }

    public function renderToConfig(IcingaConfig $config)
    {
        if ($config->isLegacy()) {
            return $this->renderToLegacyConfig($config);
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
            return $this->zone;
        }

        if ($this->hasProperty('zone_id')) {
            if (! $this->supportsImports()) {
                if ($zoneId = $this->zone_id) {
                    // Config has a lookup cache, is faster:
                    return $config->getZoneName($zoneId);
                }
            }

            try {
                if ($zoneId = $this->getResolvedProperty('zone_id')) {
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
        return $this->renderLegacyRelationProperty('host', $this->host_id, 'host_name');
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
        $blacklist = array_merge(array(
            'id',
            'object_name',
            'object_type',
        ), array() /* $this->prioritizedProperties */);

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

                return '';
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

        return c::renderKeyValue($key, c::renderString($value));
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

                return '';
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
            return $this->vars()->toConfigString();
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
    protected function renderArguments()
    {
        if ($this->supportsArguments()) {
            return $this->arguments()->toConfigString();
        } else {
            return '';
        }
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

    protected function renderObjectHeader()
    {
        return sprintf(
            "%s %s %s {\n",
            $this->getObjectTypeName(),
            $this->getType(),
            c::renderString($this->getObjectName())
        );
    }

    protected function renderAssignments()
    {
        if ($this->supportsAssignments()) {
            return $this->assignments()->toConfigString();
        } else {
            return '';
        }
    }

    protected function renderLegacyObjectHeader()
    {
        $type = strtolower($this->getType());

        if ($this->isTemplate()) {
            $name = c1::renderKeyValue(
                'name',
                c1::renderString($this->getObjectName())
            );
        } else {
            $name = c1::renderKeyValue(
                $type . '_name',
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

    public function toLegacyConfigString()
    {
        $str = implode(array(
            $this->renderLegacyObjectHeader(),
            $this->renderLegacyImports(),
            $this->renderLegacyProperties(),
            //$this->renderRanges(),
            //$this->renderArguments(),
            //$this->renderRelatedSets(),
            //$this->renderGroups(),
            //$this->renderMultiRelations(),
            //$this->renderCustomExtensions(),
            //$this->renderCustomVars(),
            //$this->renderAssignments(),
            $this->renderLegacySuffix()
        ));

        $str = $this->alignLegacyProperties($str);

        if ($this->isDisabled()) {
            return "/* --- This object has been disabled ---\n"
                . $str . "*/\n";
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
                    var_dump($m);
                    exit;
                }

                $line = '    ' . $m[1] . str_repeat(' ', $len - strlen($m[1])) . $m[2];
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
            $this->renderAssignments(),
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

    protected function getObjectName()
    {
        if ($this->hasProperty('object_name')) {
            return $this->object_name;
        } else {
            // TODO: replace with an exception once finished
            return 'ERROR: NO NAME';
        }
    }

    protected static function classByType($type)
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
        } elseif ($type === 'apiuser') {
            $type = 'apiUser';
        }

        return 'Icinga\\Module\\Director\\Objects\\' . $prefix . ucfirst($type);
    }

    public static function createByType($type, $properties = array(), Db $db = null)
    {
        $class = self::classByType($type);
        return $class::create($properties, $db);
    }

    public static function loadByType($type, $id, Db $db)
    {
        $class = self::classByType($type);
        return $class::load($id, $db);
    }

    public static function existsByType($type, $id, Db $db)
    {
        $class = self::classByType($type);
        return $class::exists($id, $db);
    }

    public static function loadAllByType($type, Db $db, $query = null, $keyColumn = 'object_name')
    {
        $class = self::classByType($type);

        if (is_array($class::create()->getKeyName())) {
            return $class::loadAll($db, $query);
        } else {
            return $class::loadAll($db, $query, $keyColumn);
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
    public function merge(IcingaObject $object)
    {
        $object = clone($object);

        if ($object->supportsCustomVars()) {
            $vars = $object->getVars();
            $object->vars = array();
        }

        $this->setProperties((array) $object->toPlainObject(null, true));

        if ($object->supportsCustomVars()) {
            $myVars = $this->vars();
            foreach ($vars as $key => $var) {
                $myVars->set($key, $var);
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
            $p = $this->getResolvedProperties();
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

            if ($chosenProperties !== null) {
                if (! in_array($v, $chosenProperties)) {
                    continue;
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

        if ($this->supportsArguments()) {
            // TODO: resolve
            $props['arguments'] = $this->arguments()->toPlainObject(
                $resolved,
                $skipDefaults
            );
        }

        if ($this->supportsAssignments()) {
            $props['assignments'] = $this->assignments()->getPlain();
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
                $props['imports'] = array();
            } else {
                $props['imports'] = $this->imports()->listImportNames();
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

        ksort($props);

        return (object) $props;
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

        if ($this->object_type === 'apply') {
            $params['id'] = $this->id;
        } else {
            $params = array('name' => $this->object_name);

            if ($this->hasProperty('host_id') && $this->host_id) {
                $params['host'] = $this->host;
            }

            if ($this->hasProperty('service_id') && $this->service_id) {
                $params['service'] = $this->service;
            }
        }

        return $params;
    }

    public function getOnDeleteUrl()
    {
        return 'director/' . strtolower($this->getShortTableName()) . 's';
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

            if ($this->differsFromDefaultvalue($k, $v)) {
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

        if ($this->supportsArguments()) {
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

        if ($this->supportsAssignments()) {
            $props['assignments'] = $this->assignments()->getUnmodifiedPlain();
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
        unset($this->arguments);


        parent::__destruct();
    }
}
