<?php

namespace Icinga\Module\Director\Objects;

use Icinga\Module\Director\Db;
use Icinga\Module\Director\Exception\NestingError;
use Icinga\Module\Director\IcingaConfig\IcingaConfig;
use Icinga\Module\Director\IcingaConfig\IcingaConfigHelper as c;

class IcingaZone extends IcingaObject
{
    protected $table = 'icinga_zone';

    protected $uuidColumn = 'uuid';

    protected $defaultProperties = [
        'id'          => null,
        'uuid'        => null,
        'object_name' => null,
        'object_type' => null,
        'disabled'    => 'n',
        'parent_id'   => null,
        'is_global'   => 'n',
    ];

    protected $booleans = [
        // Global is a reserved word in SQL, column name was prefixed
        'is_global' => 'global'
    ];

    protected $relations = [
        'parent' => 'IcingaZone',
    ];

    protected $supportsImports = true;

    protected static $globalZoneNames;

    private $endpointList;

    protected function renderCustomExtensions()
    {
        $endpoints = $this->listEndpoints();
        if (empty($endpoints)) {
            return '';
        }

        return c::renderKeyValue('endpoints', c::renderArray($endpoints));
    }

    public function isGlobal()
    {
        return $this->get('is_global') === 'y';
    }

    public static function zoneNameIsGlobal($name, Db $connection)
    {
        if (self::$globalZoneNames === null) {
            $db = $connection->getDbAdapter();
            self::setCachedGlobalZoneNames($db->fetchCol(
                $db->select()->from('icinga_zone', 'object_name')->where('is_global = ?', 'y')
            ));
        }

        return \in_array($name, self::$globalZoneNames);
    }

    public static function setCachedGlobalZoneNames($names)
    {
        self::$globalZoneNames = $names;
    }

    /**
     * Resolve related properties and reject cyclic parent hierarchies
     *
     * @return void
     *
     * @throws \Icinga\Exception\NotFoundError
     * @throws NestingError
     */
    protected function beforeStore(): void
    {
        parent::beforeStore();

        if ($parentId = $this->get('parent_id')) {
            $this->assertNoZoneLoop((int) $parentId);
        }
    }

    /**
     * Assert that there is no zone loop
     *
     * @param int $parentId
     *
     * @return void
     *
     * @throws NestingError
     */
    protected function assertNoZoneLoop(int $parentId): void
    {
        $id = (int) $this->get('id');
        $parents = [];
        $names = [];
        $db = $this->getDb();

        foreach ($db->fetchAll($db->select()->from('icinga_zone', ['id', 'parent_id', 'object_name'])) as $zone) {
            $parents[(int) $zone->id] = (int) $zone->parent_id;
            $names[(int) $zone->id] = $zone->object_name;
        }

        $chain = [$this->getObjectName()];
        $visited = [$id => true];

        while ($parentId) {
            $chain[] = $names[$parentId] ?? $parentId;

            if (isset($visited[$parentId])) {
                throw new NestingError('Loop detected: %s', implode(' -> ', $chain));
            }

            $visited[$parentId] = true;

            $parentId = $parents[$parentId] ?? 0;
        }
    }

    public function getRenderingZone(?IcingaConfig $config = null)
    {
        // If the zone has a parent zone...
        if ($this->get('parent_id')) {
            // ...we render the zone object to the parent zone
            return $this->get('parent');
        } elseif ($this->get('is_global') === 'y') {
            // ...additional global zones are rendered to our global zone...
            return $this->connection->getDefaultGlobalZoneName();
        } else {
            // ...and all the other zones are rendered to our master zone
            return $this->connection->getMasterZoneName();
        }
    }

    public function setEndpointList($list)
    {
        $this->endpointList = $list;

        return $this;
    }

    // TODO: Move this away, should be prefetchable:
    public function listEndpoints()
    {
        $id = $this->get('id');
        if ($id && $this->endpointList === null) {
            $db = $this->getDb();
            $query = $db->select()
                ->from('icinga_endpoint', 'object_name')
                ->where('zone_id = ?', $id)
                ->order('object_name');

            $this->endpointList = $db->fetchCol($query);
        }

        return $this->endpointList;
    }
}
