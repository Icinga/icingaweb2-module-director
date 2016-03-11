<?php

namespace Icinga\Module\Director\Objects;

use Icinga\Module\Director\CustomVariable\CustomVariables;
use Icinga\Module\Director\Data\Db\DbObject;
use Icinga\Module\Director\Db;
use Icinga\Module\Director\IcingaConfig\AssignRenderer;
use Icinga\Module\Director\IcingaConfig\IcingaConfigRenderer;
use Icinga\Module\Director\IcingaConfig\IcingaConfigHelper as c;
use Icinga\Data\Filter\Filter;
use Icinga\Exception\ConfigurationError;
use Icinga\Exception\ProgrammingError;
use Exception;

abstract class IcingaObject extends DbObject implements IcingaConfigRenderer
{
    protected $keyName = 'object_name';

    protected $autoincKeyName = 'id';

    protected $supportsCustomVars = false;

    protected $supportsGroups = false;

    protected $supportsRanges = false;

    protected $supportsArguments = false;

    protected $supportsImports = false;

    protected $supportsFields = false;

    protected $supportsApplyRules = false;

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

    protected $unresolvedRelatedProperties = array();

    protected $loadedRelatedSets = array();

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

    private $importedObjects;

    private $ranges;

    private $arguments;

    private $shouldBeRemoved = false;

    private $resolveCache = array();

    public function propertyIsBoolean($property)
    {
        return array_key_exists($property, $this->booleans);
    }

    public function propertyIsInterval($property)
    {
        return array_key_exists($property, $this->intervalProperties);
    }

    public function propertyIsRelatedSet($property)
    {
        return array_key_exists($property, $this->relatedSets);
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

    public function hasRelation($property)
    {
        return array_key_exists($property, $this->relations);
    }

    protected function getRelationClass($property)
    {
        return __NAMESPACE__ . '\\' . $this->relations[$property];
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

    public function supportsCustomVars()
    {
        return $this->supportsCustomVars;
    }

    public function supportsGroups()
    {
        return $this->supportsGroups;
    }

    public function supportsRanges()
    {
        return $this->supportsRanges;
    }

    public function supportsArguments()
    {
        return $this->supportsArguments;
    }

    public function supportsImports()
    {
        return $this->supportsImports;
    }

    public function supportsFields()
    {
        return $this->supportsFields;
    }

    public function supportsApplyRules()
    {
        return $this->supportsApplyRules;
    }

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
//var_dump($this->vars()); exit;
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

        foreach ($this->loadedRelatedSets as $set) {
            if ($set->hasBeenModified()) {
                return true;
            }
        }

        return parent::hasBeenModified();
    }

    protected function hasUnresolvedRelatedProperty($name)
    {
        return array_key_exists($name, $this->unresolvedRelatedProperties);
    }

    public function get($key)
    {
        if ($this->hasUnresolvedRelatedProperty($key . '_id')) {
            return $this->unresolvedRelatedProperties[$key . '_id'];
        }

        if (substr($key, -3) === '_id') {
            $short = substr($key, 0, -3);
            if ($this->hasRelation($short)) {
                if ($this->hasUnresolvedRelatedProperty($key)) {
                    $this->resolveUnresolvedRelatedProperty($key);
                }
            }
        }

        if ($this->hasRelation($key)) {
            if ($id = $this->get($key . '_id')) {
                $class = $this->getRelationClass($key);
                $object = $class::loadWithAutoIncId($id, $this->connection);
                return $object->object_name;
            }

            return null;
        }

        if ($this->propertyIsRelatedSet($key)) {
            return $this->getRelatedSet($key)->toPlainObject();
        }

        return parent::get($key);
    }

    public function set($key, $value)
    {
        if ($key === 'arguments') {
            if (is_object($value)) {
                foreach ($value as $arg => $val) {
                    $this->arguments()->set($arg, $val);
                }
            }
            return $this;

        } elseif ($key === 'vars') {
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
        }

        if ($this->propertyIsBoolean($key) && $value !== null) {
            return parent::set($key, $this->normalizeBoolean($value));
        }

        if ($this->hasRelation($key)) {
            if (strlen($value) === 0) {
                return parent::set($key . '_id', null);
            }

            $this->unresolvedRelatedProperties[$key . '_id'] = $value;
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
            if ($this->hasBeenLoadedFromDb()) {
                $this->ranges = IcingaTimePeriodRanges::loadForStoredObject($this);
            } else {
                $this->ranges = new IcingaTimePeriodRanges($this);
            }
        }

        return $this->ranges;
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

    // TODO: what happens if imports change at runtime?
    public function importedObjects()
    {
        $this->assertImportsSupport();
        if ($this->importedObjects === null) {
            $this->importedObjects = array();
            foreach ($this->imports()->listImportNames() as $import) {
                if (is_array($this->getKeyName())) {
                    // Affects services only:
                    $this->importedObjects[$import] = self::load(
                        array('object_name' => $import),
                        $this->connection
                    );
                } else {
                    $this->importedObjects[$import] = self::load($import, $this->connection);
                }
            }
        }

        return $this->importedObjects;
    }

    public function clearImportedObjects()
    {
        $this->importedObjects = null;
        return $this;
    }

    public function setImports($imports)
    {
        if (! is_array($imports)) {
            $imports = array($imports);
        }

        $this->imports()->set($imports);
        if ($this->imports()->hasBeenModified()) {
            $this->clearImportedObjects();
            $this->invalidateResolveCache();
        }
    }

    public function getImports()
    {
        return $this->imports()->listImportNames();
    }

    public function getResolvedProperty($key, $default = null)
    {
        $properties = $this->getResolvedProperties();
        if (property_exists($properties, $key)) {
            return $properties->$key;
        }

        return $default;
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
        return $this;
    }

    public function countDirectDescendants()
    {
        $db = $this->getDb();
        $table = $this->getTableName();
        $type = strtolower($this->getType());
        $query = $db->select()->from(
            array('oi' => $table . '_inheritance'),
            array('cnt' => 'COUNT(*)')
        )->where('oi.parent_' . $type . '_id = ?', (int) $this->id);

        return $db->fetchOne($query);
    }

    protected function resolve($what)
    {
        if ($this->hasResolveCached($what)) {
            return $this->getResolveCached($what);
        }

        $vals = array();
        $vals['_MERGED_']    = (object) array();
        $vals['_INHERITED_'] = (object) array();
        $vals['_ORIGINS_']   = (object) array();
        $objects = $this->importedObjects();

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
        return $filter->matches($this->flattenProperties());
    }

    protected function flattenProperties()
    {
        $db = $this->getDb();
        $obj = (object) array();
        foreach ($this->getProperties() as $k => $v) {
            $obj->$k = $v;
        }

        if ($this->supportsCustomVars()) {
/*
            foreach ($this->getVars() as $k => $v) {
                $obj->{'vars.' . $k} = $v;
            }
*/
        }

        return $obj;
    }

    public function cloneFullyResolved()
    {
        // TODO
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
                $this->vars = CustomVariables::loadForStoredObject($this);
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
        if (! $this->isApplyRule()) {
            throw new ProgrammingError('Assignments are available only for apply rules');
        }

        if (! $this->hasBeenLoadedFromDb()) {
            return array();
        }

        $db = $this->getDb();

        $query = $db->select()->from(
            array('a' => $this->getTableName() . '_assignment'),
            array(
                'filter_string' => 'a.filter_string',
            )
        )->where('a.' . $this->getShortTableName() . '_id = ?', (int) $this->id);

        return $db->fetchCol($query);
    }

    public function hasProperty($key)
    {
        if ($this->propertyIsRelatedSet($key)) {
            return true;
        }

        return parent::hasProperty($key);
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
            ->storeImports()
            ->storeRanges()
            ->storeRelatedSets()
            ->storeArguments();
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

    public function onDelete()
    {
        DirectorActivityLog::logRemoval($this, $this->connection);
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

    // Disabled is a virtual property
    protected function renderDisabled()
    {
        return '';
    }

    protected function renderProperties()
    {
        $out = '';
        $blacklist = array(
            'id',
            'object_name',
            'object_type',
        );

        foreach ($this->properties as $key => $value) {

            if (substr($key, -3) === '_id') {
                $short = substr($key, 0, -3);
                if ($this->hasUnresolvedRelatedProperty($key)) {
                    $out .= c::renderKeyValue(
                        $short, // NOT
                        c::renderString($this->$short)
                    );

                    continue;
                }
            }

            if ($value === null) {
                continue;
            }
            if (in_array($key, $blacklist)) {
                continue;
            }

            $method = 'render' . ucfirst($key);
            if (method_exists($this, $method)) {
                $out .= $this->$method($value);
            } else {
                if ($this->propertyIsBoolean($key)) {
                    if ($value !== $this->defaultProperties[$key]) {
                        $out .= c::renderKeyValue(
                            $this->booleans[$key],
                            c::renderBoolean($value)
                        );
                    }
                } elseif ($this->propertyIsInterval($key)) {
                    $out .= c::renderKeyValue(
                        $this->intervalProperties[$key],
                        c::renderInterval($value)
                    );
                } elseif (substr($key, -3) === '_id'
                     && $this->hasRelation($relKey = substr($key, 0, -3))
                ) {
                    $out .= $this->renderRelationProperty($relKey, $value);
                } else {
                    $out .= c::renderKeyValue($key, c::renderString($value));
                }
            }
        }

        return $out;
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
        if ($this->isApplyRule()) {
            $rules = $this->getAssignments();

            if (empty($rules)) {
                return '';
            }

            $filters = array();
            foreach ($rules as $rule) {
                $filters[] = AssignRenderer::forFilter(
                    Filter::fromQueryString($rule)
                )->renderAssign();
            }

            return "\n    " . implode("\n    ", $filters) . "\n";
        } else {
            return '';
        }
    }

    public function toConfigString()
    {
        return implode(array(
            $this->renderObjectHeader(),
            $this->renderImports(),
            $this->renderProperties(),
            $this->renderRanges(),
            $this->renderArguments(),
            $this->renderRelatedSets(),
            $this->renderGroups(),
            $this->renderCustomExtensions(),
            $this->renderCustomVars(),
            $this->renderAssignments(),
            $this->renderSuffix()
        ));
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
        $type = preg_replace('/^icinga_/', '', $type);

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

    public function replaceWith(IcingaObject $object)
    {
        $this->setProperties((array) $object->toPlainObject());
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
            $p = $this->getProperties();
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
                $props[$k] = $v;
            }
        }

        if ($this->supportsGroups()) {
            // TODO: resolve
            $props['groups'] = $this->groups()->listGroupNames();
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

        if ($skipDefaults) {
            if (empty($props['imports'])) {
                unset($props['imports']);
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
                    foreach ($this->importedObjects() as $parent) {
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

            if ($this->hasProperty('host_id')) {
                $params['host'] = $this->host;
            }

            if ($this->hasProperty('service_id')) {
                $params['service'] = $this->service;
            }
        }

        return $params;
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

        foreach ($this->relatedSets() as $property => $set) {
            if ($set->isEmpty()) {
                continue;
            }

            $props[$property] = $set->getPlainUnmodifiedObject();
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
