<?php

namespace Icinga\Module\Director\Db\Branch;

use Icinga\Exception\NotFoundError;
use Icinga\Module\Director\Data\Db\DbDataFormatter;
use Icinga\Module\Director\Data\Db\DbObject;
use Icinga\Module\Director\Data\Db\DbObjectTypeRegistry;
use Icinga\Module\Director\Data\Json;
use Icinga\Module\Director\Db;
use Icinga\Module\Director\Objects\IcingaObject;
use Ramsey\Uuid\UuidInterface;
use stdClass;

class BranchedObject
{
    /** @var UuidInterface */
    protected $branchUuid;

    /** @var ?DbObject */
    protected $object;

    /** @var ?stdClass */
    protected $changes;

    /** @var bool */
    protected $branchDeleted;

    /** @var bool */
    protected $branchCreated;

    /** @var UuidInterface */
    private $objectUuid;

    /** @var string */
    private $objectTable;

    /** @var bool */
    private $loadedAsBranchedObject = false;

    /**
     * @param BranchActivity $activity
     * @param Db $connection
     * @return static
     */
    public static function withActivity(BranchActivity $activity, Db $connection)
    {
        return self::loadOptional(
            $connection,
            $activity->getObjectTable(),
            $activity->getObjectUuid(),
            $activity->getBranchUuid()
        )->applyActivity($activity, $connection);
    }

    public function store(Db $connection)
    {
        if ($this->object && ! $this->object->hasBeenModified() && empty($this->changes)) {
            return false;
        }
        $db = $connection->getDbAdapter();

        $properties = [
            'branch_deleted' => $this->branchDeleted ? 'y' : 'n',
            'branch_created' => $this->branchCreated ? 'y' : 'n',
        ] + $this->prepareChangedProperties();

        $table = 'branched_' . $this->objectTable;
        if ($this->loadedAsBranchedObject) {
            return $db->update(
                $table,
                $properties,
                $this->prepareWhereString($connection)
            ) === 1;
        } else {
            try {
                return $db->insert($table, $this->prepareKeyProperties($connection) + $properties) === 1;
            } catch (\Exception $e) {
                var_dump($e->getMessage());
                var_dump($this->prepareKeyProperties($connection) + $properties);
                exit;
            }
        }
    }

    public function delete(Db $connection)
    {
        $db = $connection->getDbAdapter();
        $table = 'branched_' . $this->objectTable;
        $branchCreated = $db->fetchOne($this->filterQuery($db->select()->from($table, 'branch_created'), $connection));
        // We do not want to nullify all properties, therefore: delete & insert
        $db->delete($table, $this->prepareWhereString($connection));

        if (! $branchCreated) {
            // No need to insert a deleted object in case this object lived in this branch only
            return $db->insert($table, $this->prepareKeyProperties($connection) + [
                'branch_deleted' => 'y',
                'branch_created' => 'n',
            ]) === 1;
        }

        return true;
    }

    protected function prepareKeyProperties(Db $connection)
    {
        return [
            'uuid'        => $connection->quoteBinary($this->objectUuid->getBytes()),
            'branch_uuid' => $connection->quoteBinary($this->branchUuid->getBytes()),
        ];
    }

    protected function prepareWhereString(Db $connection)
    {
        $db = $connection->getDbAdapter();
        $objectUuid = $connection->quoteBinary($this->objectUuid->getBytes());
        $branchUuid = $connection->quoteBinary($this->branchUuid->getBytes());

        return $db->quoteInto('uuid = ?', $objectUuid) . $db->quoteInto(' AND branch_uuid = ?', $branchUuid);
    }

    /**
     * @param \Zend_Db_Select $query
     * @param Db $connection
     * @return \Zend_Db_Select
     */
    protected function filterQuery(\Zend_Db_Select $query, Db $connection)
    {
        return $query->where('uuid = ?', $connection->quoteBinary($this->objectUuid->getBytes()))
            ->where('branch_uuid = ?', $connection->quoteBinary($this->branchUuid->getBytes()));
    }

    protected function prepareChangedProperties()
    {
        $properties = (array) $this->changes;

        foreach (BranchSettings::ENCODED_DICTIONARIES as $property) {
            $this->combineFlatDictionaries($properties, $property);
        }
        foreach (BranchSettings::ENCODED_DICTIONARIES as $property) {
            if (isset($properties[$property])) {
                $properties[$property] = Json::encode($properties[$property]);
            }
        }
        foreach (BranchSettings::ENCODED_ARRAYS as $property) {
            if (isset($properties[$property])) {
                $properties[$property] = Json::encode($properties[$property]);
            }
        }
        foreach (BranchSettings::RELATED_SETS as $property) {
            if (isset($properties[$property])) {
                $properties[$property] = Json::encode($properties[$property]);
            }
        }
        $setNull = [];
        if (array_key_exists('disabled', $properties) && $properties['disabled'] === null) {
            unset($properties['disabled']);
        }
        foreach ($properties as $key => $value) {
            if ($value === null) {
                $setNull[] = $key;
            }
        }
        if (empty($setNull)) {
            $properties['set_null'] = null;
        } else {
            $properties['set_null'] = Json::encode($setNull);
        }

        return $properties;
    }

    protected function combineFlatDictionaries(&$properties, $prefix)
    {
        $vars = [];
        $length = strlen($prefix) + 1;
        foreach ($properties as $key => $value) {
            if (substr($key, 0, $length) === "$prefix.") {
                $vars[substr($key, $length)] = $value;
            }
        }
        if (! empty($vars)) {
            foreach (array_keys($vars) as $key) {
                unset($properties["$prefix.$key"]);
            }
            $properties[$prefix] = (object) $vars;
        }
    }

    public function applyActivity(BranchActivity $activity, Db $connection)
    {
        if ($activity->isActionDelete()) {
            throw new \RuntimeException('Cannot apply a delete action');
        }
        if ($activity->isActionCreate()) {
            if ($this->hasBeenTouchedByBranch()) {
                throw new \RuntimeException('Cannot apply a CREATE activity to an already branched object');
            } else {
                $this->branchCreated = true;
            }
        }

        $dummyObject = IcingaObject::createByType(
            $this->objectTable,
            [],
            $connection
        );

        foreach ($activity->getModifiedProperties()->jsonSerialize() as $key => $value) {
            if ($dummyObject->propertyIsBoolean($key)) {
                $value = DbDataFormatter::normalizeBoolean($value);
            }

            $this->changes[$key] = $value;
        }

        return $this;
    }

    /**
     * @param Db $connection
     * @param string $objectTable
     * @param UuidInterface $uuid
     * @param Branch $branch
     * @return static
     * @throws NotFoundError
     */
    public static function load(Db $connection, $objectTable, UuidInterface $uuid, Branch $branch)
    {
        $object = static::loadOptional($connection, $objectTable, $uuid, $branch->getUuid());
        if ($object->getOriginalDbObject() === null && ! $object->hasBeenTouchedByBranch()) {
            throw new NotFoundError('Not found');
        }

        return $object;
    }

    /**
     * @return bool
     */
    public function hasBeenTouchedByBranch()
    {
        return $this->loadedAsBranchedObject;
    }

    /**
     * @return bool
     */
    public function hasBeenDeletedByBranch()
    {
        return $this->branchDeleted;
    }

    /**
     * @return bool
     */
    public function hasBeenCreatedByBranch()
    {
        return $this->branchCreated;
    }

    /**
     * @return ?DbObject
     */
    public function getOriginalDbObject()
    {
        return $this->object;
    }

    /**
     * @return ?DbObject
     */
    public function getBranchedDbObject(Db $connection)
    {
        if ($this->object) {
            $branched = DbObjectTypeRegistry::newObject($this->objectTable, [], $connection);
            // object_type first, to avoid:
            // I can only assign for applied objects or objects with native support for assignments
            if ($this->object->hasProperty('object_type')) {
                $branched->set('object_type', $this->object->get('object_type'));
            }
            $branched->set('id', $this->object->get('id'));
            $branched->set('uuid', $this->object->get('uuid'));
            foreach ((array) $this->object->toPlainObject(false, true) as $key => $value) {
                if ($key === 'object_type') {
                    continue;
                }
                $branched->set($key, $value);
            }
        } else {
            $branched = DbObjectTypeRegistry::newObject($this->objectTable, [], $connection);
            $branched->setUniqueId($this->objectUuid);
        }
        if ($this->changes === null) {
            return $branched;
        }
        foreach ($this->changes as $key => $value) {
            if ($key === 'set_null') {
                if ($value !== null) {
                    foreach ($value as $k) {
                        $branched->set($k, null);
                    }
                }
            } else {
                $branched->set($key, $value);
            }
        }

        return $branched;
    }

    /**
     * @return UuidInterface
     */
    public function getBranchUuid()
    {
        return $this->branchUuid;
    }

    /**
     * @param Db $connection
     * @param string $table
     * @param UuidInterface $uuid
     * @param ?UuidInterface $branchUuid
     * @return static
     */
    protected static function loadOptional(
        Db $connection,
        $table,
        UuidInterface $uuid,
        ?UuidInterface $branchUuid = null
    ) {
        $class = DbObjectTypeRegistry::classByType($table);
        if ($row = static::optionalTableRowByUuid($connection, $table, $uuid)) {
            $object = $class::fromDbRow((array) $row, $connection);
        } else {
            $object = null;
        }

        $self = new static();
        $self->object = $object;
        $self->objectUuid = $uuid;
        $self->branchUuid = $branchUuid;
        $self->objectTable = $table;

        if ($branchUuid && $row = static::optionalBranchedTableRowByUuid($connection, $table, $uuid, $branchUuid)) {
            if ($row->branch_deleted === 'y') {
                $self->branchDeleted = true;
            } elseif ($row->branch_created === 'y') {
                $self->branchCreated = true;
            }
            $self->changes = BranchSettings::normalizeBranchedObjectFromDb($row);
            $self->loadedAsBranchedObject = true;
        }

        return $self;
    }

    public static function exists(
        Db $connection,
        $table,
        UuidInterface $uuid,
        ?UuidInterface $branchUuid = null
    ) {
        if (static::optionalTableRowByUuid($connection, $table, $uuid)) {
            return true;
        }

        if ($branchUuid && static::optionalBranchedTableRowByUuid($connection, $table, $uuid, $branchUuid)) {
            return true;
        }

        return false;
    }

    /**
     * @param Db $connection
     * @param string $table
     * @param UuidInterface $uuid
     * @return stdClass|boolean
     */
    protected static function optionalTableRowByUuid(Db $connection, $table, UuidInterface $uuid)
    {
        $db = $connection->getDbAdapter();

        return $db->fetchRow(
            $db->select()->from($table)->where('uuid = ?', $connection->quoteBinary($uuid->getBytes()))
        );
    }

    /**
     * @param Db $connection
     * @param string $table
     * @param UuidInterface $uuid
     * @return stdClass|boolean
     */
    protected static function optionalBranchedTableRowByUuid(
        Db $connection,
        $table,
        UuidInterface $uuid,
        UuidInterface $branchUuid
    ) {
        $db = $connection->getDbAdapter();

        $query = $db->select()
            ->from("branched_$table")
            ->where('uuid = ?', $connection->quoteBinary($uuid->getBytes()))
            ->where('branch_uuid = ?', $connection->quoteBinary($branchUuid->getBytes()));

        return $db->fetchRow($query);
    }

    protected function __construct()
    {
    }
}
