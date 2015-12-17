<?php

namespace Icinga\Module\Director\Objects;

use Icinga\Module\Director\CustomVariable\CustomVariables;
use Icinga\Module\Director\Data\Db\DbObject;
use Icinga\Module\Director\Db;
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

    protected $type;

    /* key/value!! */
    protected $booleans = array();

    // Property suffixed with _id must exist
    protected $relations = array(
        // property => PropertyClass
    );

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
        $class = $this->getRelationClass($property);
        $object = $class::loadWithAutoIncId($id, $this->connection);
        return $object->object_name;
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

    public function hasBeenModified()
    {
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

        return parent::hasBeenModified();
    }

    public function set($key, $value)
    {
        if ($key === 'groups') {
            $this->groups()->set($value);
            return $this;

        } elseif ($key === 'imports') {
            $this->imports()->set($value);
            return $this;

        } elseif ($key === 'arguments') {
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
        }

        if ($this->propertyIsBoolean($key) && $value !== null) {
            if ($value === 'y' || $value === '1' || $value === true || $value === 1) {
                return parent::set($key, 'y');
            } elseif ($value === 'n' || $value === '0' || $value === false || $value === 0) {
                return parent::set($key, 'n');
            } else {
                throw new ProgrammingError(
                    'Got invalid boolean: %s',
                    var_export($value, 1)
                );
            }
        }

        if ($this->hasRelation($key)) {
            if (! $value) {
                return parent::set($key . '_id', null);
            }

            $class = $this->getRelationClass($key);
            $object = $class::load($value, $this->connection);
            if (in_array($object->object_type, array('object', 'external_object'))) {
                return parent::set($key . '_id', $object->id);
            }
            // TODO: what shall we do if it is a template? Fail?
        }

        return parent::set($key, $value);
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

    public function getResolvedProperty($key)
    {
        $properties = $this->getResolvedProperties();
        if (property_exists($properties, $key)) {
            return $properties->$key;
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
            $vars->$key = $var->getValue();
        }

        return $vars;
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

        $blacklist = array('id', 'object_type', 'object_name');
        foreach ($objects as $name => $object) {
            $origins = $object->$getOrigins();

            foreach ($object->$getInherited() as $key => $value) {
                if (in_array($key, $blacklist)) continue;
                // $vals[$name]->$key = $value;
                $vals['_MERGED_']->$key = $value;
                $vals['_INHERITED_']->$key = $value;
                $vals['_ORIGINS_']->$key = $origins->$key;
            }

            foreach ($object->$get() as $key => $value) {
                if ($value === null) continue;
                if (in_array($key, $blacklist)) continue;
                $vals['_MERGED_']->$key = $value;
                $vals['_INHERITED_']->$key = $value;
                $vals['_ORIGINS_']->$key = $name;
            }
        }

        foreach ($this->$get() as $key => $value) {
            if ($value === null) continue;

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
            ->storeArguments();
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

    protected function renderProperties()
    {
        $out = '';
        $blacklist = array(
            'id',
            'object_name',
            'object_type',
        );

        foreach ($this->properties as $key => $value) {

            if ($value === null) continue;
            if (in_array($key, $blacklist)) continue;

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
                } elseif (
                    substr($key, -3) === '_id'
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
        // TODO: db field should be int
        if (ctype_digit($this->$key)) {
            $value = (int) $this->$key;
        } else {
            // TODO: do this at munge time
            $parts = preg_split('/\s+/', $this->$key, -1, PREG_SPLIT_NO_EMPTY);
            $value = 0;
            foreach ($parts as $part) {
                if (! preg_match('/^(\d+)([dhms]?)$/', $part, $m)) {
                    throw new ConfigurationError(
                        '"%s" is not a valid time (duration) definition',
                        $this->$key
                    );
                }
                switch ($m[2]) {
                    case 'd':
                        $value += $m[1] * 86400;
                        break;
                    case 'h':
                        $value += $m[1] * 3600;
                        break;
                    case 'm':
                        $value += $m[1] * 60;
                        break;
                    default:
                        $value += (int) $m[1];
                }
            }
        }

        if ($value > 0 && $value % 60 === 0) {
            $value = (int) ($value / 60) . 'm';
        }

        return c::renderKeyValue($key, $value);
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

    protected function renderZone_id()
    {
        // We do not render zone properties, objects are stored to zone dirs
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

    public function toConfigString()
    {
        return implode(array(
            $this->renderObjectHeader(),
            $this->renderImports(),
            $this->renderProperties(),
            $this->renderRanges(),
            $this->renderArguments(),
            $this->renderGroups(),
            $this->renderCustomExtensions(),
            $this->renderCustomVars(),
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
        if ($type === 'datalistEntry') $keyColumn = 'entry_name';
        $class = self::classByType($type);
        return $class::loadAll($db, $query, $keyColumn);
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

    public function toPlainObject(
        $resolved = false,
        $skipNull = false,
        array $chosenProperties = null
    ) {
        $props = array();

        if ($resolved) {
            $p = $this->getResolvedProperties();
        } else {
            $p = $this->getProperties();
        }

        foreach ($p as $k => $v) {

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

            if ($chosenProperties !== null) {
                if (! in_array($v, $chosenProperties)) {
                    continue;
                }
            }

            // TODO: Do not ship null properties based on flag?
            if (!$skipNull || $v !== null) {
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

        ksort($props);

        return (object) $props;
    }

    public function toJson($resolved = false)
    {
        return json_encode($this->toPlainObject($resolved));
    }

    public function __toString()
    {
        try {
            return $this->toConfigString();
        } catch (Exception $e) {
            trigger_error($e);
            $previousHandler = set_exception_handler(function () {});
            restore_error_handler();
            if ($previousHandler !== null) {
                call_user_func($previousHandler, $e);
                die();
            } else {
                die($e->getMessage());
            }
        }
    }
}
