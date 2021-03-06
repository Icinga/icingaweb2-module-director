<?php

namespace Icinga\Module\Director\Db;

use Icinga\Module\Director\Application\MemoryLimit;
use Icinga\Module\Director\Data\Db\DbConnection;
use Icinga\Module\Director\Db;
use Icinga\Module\Director\Db\Cache\PrefetchCache;
use Icinga\Module\Director\Objects\GroupMembershipResolver;
use Icinga\Module\Director\Objects\IcingaObject;
use Icinga\Module\Director\Objects\IcingaObjectGroup;

abstract class MembershipHousekeeping
{
    protected $type;

    protected $groupType;

    protected $connection;

    /** @var GroupMembershipResolver */
    protected $resolver;

    /** @var IcingaObject[] */
    protected $objects;

    /** @var IcingaObjectGroup[] */
    protected $groups;

    protected $prepared = false;

    protected static $instances = [];

    public function __construct(Db $connection)
    {
        $this->connection = $connection;

        if ($this->groupType === null) {
            $this->groupType = $this->type . 'Group';
        }
    }

    /**
     * @param string       $type
     * @param DbConnection $connection
     *
     * @return static
     */
    public static function instance($type, $connection)
    {
        if (! array_key_exists($type, self::$instances)) {
            /** @var MembershipHousekeeping $class */
            $class = 'Icinga\\Module\\Director\\Db\\' . ucfirst($type) . 'MembershipHousekeeping';

            /** @var MembershipHousekeeping $helper */
            self::$instances[$type] = new $class($connection);
        }

        return self::$instances[$type];
    }

    protected function prepare()
    {
        if ($this->prepared) {
            return $this;
        }

        $this->prepareCache();
        $this->resolver()->defer();

        $this->objects = IcingaObject::loadAllByType($this->type, $this->connection);
        $this->resolver()->addObjects($this->objects);

        $this->groups = IcingaObject::loadAllByType($this->groupType, $this->connection);
        $this->resolver()->addGroups($this->groups);

        MemoryLimit::raiseTo('1024M');

        $this->prepared = true;

        return $this;
    }

    public function check()
    {
        $this->prepare();

        $resolver = $this->resolver()->checkDb();

        return array($resolver->getNewMappings(), $resolver->getOutdatedMappings());
    }

    public function update()
    {
        $this->prepare();

        $this->resolver()->refreshDb(true);

        return true;
    }

    protected function prepareCache()
    {
        PrefetchCache::initialize($this->connection);

        IcingaObject::prefetchAllRelationsByType($this->type, $this->connection);
    }

    protected function resolver()
    {
        if ($this->resolver === null) {
            /** @var GroupMembershipResolver $class */
            $class = 'Icinga\\Module\\Director\\Objects\\' . ucfirst($this->type) . 'GroupMembershipResolver';
            $this->resolver = new $class($this->connection);
        }

        return $this->resolver;
    }

    /**
     * @return IcingaObject[]
     */
    public function getObjects()
    {
        return $this->objects;
    }

    /**
     * @return IcingaObjectGroup[]
     */
    public function getGroups()
    {
        return $this->groups;
    }
}
