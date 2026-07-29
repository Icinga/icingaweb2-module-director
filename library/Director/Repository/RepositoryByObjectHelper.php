<?php

namespace Icinga\Module\Director\Repository;

use Icinga\Authentication\Auth;
use Icinga\Module\Director\Db;
use Icinga\Module\Director\Objects\IcingaObject;
use RuntimeException;

trait RepositoryByObjectHelper
{
    protected $type;

    /** @var Db */
    protected $connection;

    /** @var Auth */
    protected static $auth;

    /** @var static[] */
    protected static $instances = [];

    protected function __construct($type, Db $connection)
    {
        $this->type = $type;
        $this->connection = $connection;
    }

    /**
     * @param string $type
     * @return bool
     */
    public static function hasInstanceForType($type)
    {
        return array_key_exists($type, self::$instances);
    }

    /**
     * @param string $type
     * @param Db $connection
     * @return static
     */
    public static function instanceByType($type, Db $connection)
    {
        if (! static::hasInstanceForType($type)) {
            self::$instances[$type] = new static($type, $connection);
        }

        return self::$instances[$type];
    }

    /**
     * @param IcingaObject $object
     * @return bool
     */
    public static function hasInstanceForObject(IcingaObject $object)
    {
        return static::hasInstanceForType($object->getShortTableName());
    }

    /**
     * @param IcingaObject $object
     * @param ?Db $connection
     * @return static
     */
    public static function instanceByObject(IcingaObject $object, ?Db $connection = null)
    {
        if (null === $connection) {
            $connection = $object->getConnection();
        }

        if (! $connection) {
            throw new RuntimeException(sprintf(
                'Cannot use repository for %s "%s" as it has no DB connection',
                $object->getShortTableName(),
                $object->getObjectName()
            ));
        }

        return static::instanceByType(
            $object->getShortTableName(),
            $connection
        );
    }

    protected static function auth()
    {
        if (self::$auth === null) {
            self::$auth = Auth::getInstance();
        }

        return self::$auth;
    }

    protected static function clearInstances()
    {
        self::$instances = [];
    }
}
