<?php

namespace Icinga\Module\Director\Repository;

use Icinga\Exception\ProgrammingError;
use Icinga\Module\Director\Db;
use Icinga\Module\Director\Objects\IcingaObject;

trait RepositoryByObjectHelper
{
    protected $type;

    /** @var Db */
    protected $connection;

    /** @var static[] */
    protected static $instances = [];

    protected function __construct($type, Db $connection)
    {
        $this->type = $type;
        $this->connection = $connection;
    }

    /**
     * @param $type
     * @param Db $connection
     * @return static
     */
    public static function instanceByType($type, Db $connection)
    {
        if (!array_key_exists($type, self::$instances)) {
            self::$instances[$type] = new static($type, $connection);
        }

        return self::$instances[$type];
    }

    /**
     * @param IcingaObject $object
     * @param Db|null $connection
     * @return static
     * @throws ProgrammingError
     */
    public static function instanceByObject(IcingaObject $object, Db $connection = null)
    {
        if (null === $connection) {
            $connection = $object->getConnection();
        }

        if (! $connection) {
            throw new ProgrammingError(
                'Cannot use repository for %s "%s" as it has no DB connection',
                $object->getShortTableName(),
                $object->getObjectName()
            );
        }

        return static::instanceByType(
            $object->getShortTableName(),
            $connection
        );
    }
}
