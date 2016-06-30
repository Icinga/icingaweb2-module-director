<?php

namespace Icinga\Module\Director\Db\Cache;

use Icinga\Exception\ProgrammingError;
use Icinga\Module\Director\Db;
use Icinga\Module\Director\Objects\IcingaObject;

class PrefetchCache
{
    protected $db;

    protected static $instance;

    protected $caches = array();

    protected $varsCaches = array();

    protected $groupsCaches = array();

    public static function initialize(Db $db)
    {
        self::$instance = new static($db);
    }

    protected function __construct(Db $db)
    {
        $this->db = $db;
    }

    public static function instance()
    {
        if (static::$instance === null) {
            throw new ProgrammingError('Prefetch cache has not been loaded');
        }

        return static::$instance;
    }

    public static function forget()
    {
        self::$instance = null;
    }

    public static function shouldBeUsed()
    {
        return self::$instance !== null;
    }

    public function vars(IcingaObject $object)
    {
        return $this->varsCache($object)->getVarsForObject($object);
    }

    public function groups(IcingaObject $object)
    {
        return $this->groupsCache($object)->getGroupsForObject($object);
    }

    public function byObjectType($type)
    {
        if (! array_key_exists($type, $this->caches)) {
            $this->caches[$type] = new ObjectCache($type);
        }

        return $this->caches[$type];
    }

    protected function varsCache(IcingaObject $object)
    {
        $key = $object->getShortTableName();

        if (! array_key_exists($key, $this->varsCaches)) {
            $this->varsCaches[$key] = new CustomVariableCache($object);
        }

        return $this->varsCaches[$key];
    }

    protected function groupsCache(IcingaObject $object)
    {
        $key = $object->getShortTableName();

        if (! array_key_exists($key, $this->groupsCaches)) {
            $this->groupsCaches[$key] = new GroupMembershipCache($object);
        }

        return $this->groupsCaches[$key];
    }

    public function __destruct()
    {
        unset($this->caches);
        unset($this->groupsCaches);
        unset($this->varsCaches);
    }
}
