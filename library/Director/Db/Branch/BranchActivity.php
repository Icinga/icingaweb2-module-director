<?php

namespace Icinga\Module\Director\Db\Branch;

use Icinga\Authentication\Auth;
use Icinga\Exception\NotFoundError;
use Icinga\Module\Director\Data\Db\DbObject;
use Icinga\Module\Director\Data\Db\DbObjectTypeRegistry;
use Icinga\Module\Director\Data\Json;
use Icinga\Module\Director\Data\SerializableValue;
use Icinga\Module\Director\Db;
use Icinga\Module\Director\Objects\DirectorActivityLog;
use Icinga\Module\Director\Objects\IcingaObject;
use InvalidArgumentException;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use RuntimeException;

class BranchActivity
{
    const DB_TABLE = 'director_branch_activity';

    const ACTION_CREATE = DirectorActivityLog::ACTION_CREATE;
    const ACTION_MODIFY = DirectorActivityLog::ACTION_MODIFY;
    const ACTION_DELETE = DirectorActivityLog::ACTION_DELETE;

    /** @var int */
    protected $timestampNs;

    /** @var UuidInterface */
    protected $objectUuid;

    /** @var UuidInterface */
    protected $branchUuid;

    /** @var string create, modify, delete */
    protected $action;

    /** @var string */
    protected $objectTable;

    /** @var string */
    protected $author;

    /** @var SerializableValue */
    protected $modifiedProperties;

    /** @var ?SerializableValue */
    protected $formerProperties;

    public function __construct(
        UuidInterface     $objectUuid,
        UuidInterface     $branchUuid,
        $action,
        $objectType,
        $author,
        SerializableValue $modifiedProperties,
        SerializableValue $formerProperties
    ) {
        $this->objectUuid = $objectUuid;
        $this->branchUuid = $branchUuid;
        $this->action = $action;
        $this->objectTable = $objectType;
        $this->author = $author;
        $this->modifiedProperties = $modifiedProperties;
        $this->formerProperties = $formerProperties;
    }

    public static function deleteObject(DbObject $object, Branch $branch)
    {
        return new static(
            $object->getUniqueId(),
            $branch->getUuid(),
            self::ACTION_DELETE,
            $object->getTableName(),
            Auth::getInstance()->getUser()->getUsername(),
            SerializableValue::fromSerialization(null),
            SerializableValue::fromSerialization(self::getFormerObjectProperties($object))
        );
    }

    public static function forDbObject(DbObject $object, Branch $branch)
    {
        if (! $object->hasBeenModified()) {
            throw new InvalidArgumentException('Cannot get modifications for unmodified object');
        }
        if (! $branch->isBranch()) {
            throw new InvalidArgumentException('Branch activity requires an active branch');
        }

        $author = Auth::getInstance()->getUser()->getUsername();
        if ($object instanceof IcingaObject && $object->shouldBeRemoved()) {
            $action = self::ACTION_DELETE;
            $old = self::getFormerObjectProperties($object);
            $new = null;
        } elseif ($object->hasBeenLoadedFromDb()) {
            $action = self::ACTION_MODIFY;
            $old = self::getFormerObjectProperties($object);
            $new = self::getObjectProperties($object);
        } else {
            $action = self::ACTION_CREATE;
            $old = null;
            $new = self::getObjectProperties($object);
        }

        if ($new !== null) {
            $new = PlainObjectPropertyDiff::calculate(
                $old,
                $new
            );
        }

        return new static(
            $object->getUniqueId(),
            $branch->getUuid(),
            $action,
            $object->getTableName(),
            $author,
            SerializableValue::fromSerialization($new),
            SerializableValue::fromSerialization($old)
        );
    }

    public static function fixFakeTimestamp($timestampNs)
    {
        if ($timestampNs < 1600000000 * 1000000) {
            // fake TS for cloned branch in sync preview
            return (int) $timestampNs * 1000000;
        }

        return $timestampNs;
    }

    public function applyToDbObject(DbObject $object)
    {
        if (!$this->isActionModify()) {
            throw new RuntimeException('Only BranchActivity instances with action=modify can be applied');
        }

        foreach ($this->getModifiedProperties()->jsonSerialize() as $key => $value) {
            $object->set($key, $value);
        }

        return $object;
    }

    /**
     * Hint: $connection is required, because setting groups triggered loading them.
     *       Should be investigated, as in theory $hostWithoutConnection->groups = 'group'
     *       is expected to work
     * @param Db $connection
     * @return DbObject|string
     */
    public function createDbObject(Db $connection)
    {
        if (!$this->isActionCreate()) {
            throw new RuntimeException('Only BranchActivity instances with action=create can create objects');
        }

        $class = DbObjectTypeRegistry::classByType($this->getObjectTable());
        $object = $class::create([], $connection);
        $object->setUniqueId($this->getObjectUuid());
        foreach ($this->getModifiedProperties()->jsonSerialize() as $key => $value) {
            $object->set($key, $value);
        }

        return $object;
    }

    public function deleteDbObject(DbObject $object)
    {
        if (!$this->isActionDelete()) {
            throw new RuntimeException('Only BranchActivity instances with action=delete can delete objects');
        }

        return $object->delete();
    }

    public static function load($ts, Db $connection)
    {
        $db = $connection->getDbAdapter();
        $row = $db->fetchRow(
            $db->select()->from('director_branch_activity')->where('timestamp_ns = ?', $ts)
        );

        if ($row) {
            return static::fromDbRow($row);
        }

        throw new NotFoundError('Not found');
    }

    protected static function fixPgResource(&$value)
    {
        if (is_resource($value)) {
            $value = stream_get_contents($value);
        }
    }

    public static function fromDbRow($row)
    {
        static::fixPgResource($row->object_uuid);
        static::fixPgResource($row->branch_uuid);
        $activity = new static(
            Uuid::fromBytes($row->object_uuid),
            Uuid::fromBytes($row->branch_uuid),
            $row->action,
            $row->object_table,
            $row->author,
            SerializableValue::fromSerialization(Json::decodeOptional($row->modified_properties)),
            SerializableValue::fromSerialization(Json::decodeOptional($row->former_properties))
        );
        $activity->timestampNs = $row->timestamp_ns;

        return $activity;
    }

    /**
     * Must be run in a transaction! Repeatable read?
     * @param Db $connection
     * @throws \Icinga\Module\Director\Exception\JsonEncodeException
     * @throws \Zend_Db_Adapter_Exception
     */
    public function store(Db $connection)
    {
        if ($this->timestampNs !== null) {
            throw new InvalidArgumentException(sprintf(
                'Cannot store activity with a given timestamp: %s',
                $this->timestampNs
            ));
        }
        $db = $connection->getDbAdapter();
        $last = $db->fetchRow(
            $db->select()->from('director_branch_activity', ['timestamp_ns' => 'MAX(timestamp_ns)'])
        );
        if (PHP_INT_SIZE !== 8) {
            throw new RuntimeException('PHP with 64bit integer support is required');
        }
        $timestampNs = (int) floor(microtime(true) * 1000000);
        if ($last) {
            if ($last->timestamp_ns >= $timestampNs) {
                $timestampNs = $last + 1;
            }
        }
        $old = Json::encode($this->formerProperties);
        $new = Json::encode($this->modifiedProperties);

        $db->insert(self::DB_TABLE, [
            'timestamp_ns'    => $timestampNs,
            'object_uuid'     => $connection->quoteBinary($this->objectUuid->getBytes()),
            'branch_uuid'     => $connection->quoteBinary($this->branchUuid->getBytes()),
            'action'          => $this->action,
            'object_table'    => $this->objectTable,
            'author'          => $this->author,
            'former_properties'   => $old,
            'modified_properties' => $new,
        ]);
    }

    /**
     * @return int
     */
    public function getTimestampNs()
    {
        return $this->timestampNs;
    }

    /**
     * @return int
     */
    public function getTimestamp()
    {
        return (int) floor(BranchActivity::fixFakeTimestamp($this->timestampNs) / 1000000);
    }

    /**
     * @return UuidInterface
     */
    public function getObjectUuid()
    {
        return $this->objectUuid;
    }

    /**
     * @return UuidInterface
     */
    public function getBranchUuid()
    {
        return $this->branchUuid;
    }

    /**
     * @return string
     */
    public function getObjectName()
    {
        if ($this->objectTable === BranchSupport::TABLE_ICINGA_SERVICE && $host = $this->getProperty('host')) {
            $suffix = " ($host)";
        } else {
            $suffix = '';
        }

        return $this->getProperty('object_name', 'unknown object name') . $suffix;
    }

    /**
     * @return string
     */
    public function getAction()
    {
        return $this->action;
    }

    public function isActionDelete()
    {
        return $this->action === self::ACTION_DELETE;
    }

    public function isActionCreate()
    {
        return $this->action === self::ACTION_CREATE;
    }

    public function isActionModify()
    {
        return $this->action === self::ACTION_MODIFY;
    }

    /**
     * @return string
     */
    public function getObjectTable()
    {
        return $this->objectTable;
    }

    /**
     * @return string
     */
    public function getAuthor()
    {
        return $this->author;
    }

    /**
     * @return ?SerializableValue
     */
    public function getModifiedProperties()
    {
        return $this->modifiedProperties;
    }

    /**
     * @return ?SerializableValue
     */
    public function getFormerProperties()
    {
        return $this->formerProperties;
    }

    public function getProperty($key, $default = null)
    {
        if ($this->modifiedProperties) {
            $properties = $this->modifiedProperties->jsonSerialize();
            if (isset($properties->$key)) {
                return $properties->$key;
            }
        }
        if ($this->formerProperties) {
            $properties = $this->formerProperties->jsonSerialize();
            if (isset($properties->$key)) {
                return $properties->$key;
            }
        }

        return $default;
    }

    protected static function getFormerObjectProperties(DbObject $object)
    {
        if (! $object instanceof IcingaObject) {
            throw new RuntimeException('Plain object helpers for DbObject must be implemented');
        }

        return (array) $object->getPlainUnmodifiedObject();
    }

    protected static function getObjectProperties(DbObject $object)
    {
        if (! $object instanceof IcingaObject) {
            throw new RuntimeException('Plain object helpers for DbObject must be implemented');
        }

        return (array) $object->toPlainObject(false, true);
    }
}
