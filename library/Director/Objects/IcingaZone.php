<?php

namespace Icinga\Module\Director\Objects;

use Icinga\Module\Director\Db;
use Icinga\Module\Director\DirectorObject\Automation\ExportInterface;
use Icinga\Module\Director\Exception\DuplicateKeyException;
use Icinga\Module\Director\IcingaConfig\IcingaConfig;
use Icinga\Module\Director\IcingaConfig\IcingaConfigHelper as c;

class IcingaZone extends IcingaObject implements ExportInterface
{
    protected $table = 'icinga_zone';

    protected $defaultProperties = [
        'id'          => null,
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

    public function getUniqueIdentifier()
    {
        return $this->getObjectName();
    }

    /**
     * @return object
     * @throws \Icinga\Exception\NotFoundError
     */
    public function export()
    {
        return $this->toPlainObject();
    }

    /**
     * @param $plain
     * @param Db $db
     * @param bool $replace
     * @return static
     * @throws DuplicateKeyException
     * @throws \Icinga\Exception\NotFoundError
     */
    public static function import($plain, Db $db, $replace = false)
    {
        $properties = (array) $plain;
        $name = $properties['object_name'];

        if ($replace && static::exists($name, $db)) {
            $object = static::load($name, $db);
        } elseif (static::exists($name, $db)) {
            throw new DuplicateKeyException(
                'Zone "%s" already exists',
                $name
            );
        } else {
            $object = static::create([], $db);
        }
        $object->setProperties($properties);
        return $object;
    }

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

    public function getRenderingZone(IcingaConfig $config = null)
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
