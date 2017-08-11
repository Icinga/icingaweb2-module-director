<?php

namespace Icinga\Module\Director\Repository;

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
     * @return static
     */
    public static function instanceByObject(IcingaObject $object, Db $connection = null)
    {
        if (null === $connection) {
            $connection = $object->getConnection();
        }

        if (! $connection) {
            var_dump($object->hasBeenLoadedFromDb()); exit;
            echo '<pre>';
            debug_print_backtrace();
            echo '</pre>';
            throw new \Exception('SDFA');
        }
        return static::instanceByType(
            $object->getShortTableName(),
            $connection
        );
    }
}
