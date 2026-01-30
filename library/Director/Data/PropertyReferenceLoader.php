<?php

namespace Icinga\Module\Director\Data;

use gipfl\ZfDb\Adapter\Adapter;
use Icinga\Module\Director\Db;
use Icinga\Module\Director\Objects\IcingaObject;
use Ramsey\Uuid\Uuid;

class PropertyReferenceLoader
{
    /** @var Adapter|\Zend_Db_Adapter_Abstract */
    protected $db;

    public function __construct(Db $connection)
    {
        $this->db = $connection->getDbAdapter();
    }

    /**
     * Load properties referenced by the object
     *
     * @param IcingaObject $object
     *
     * @return array
     */
    public function loadFor(IcingaObject $object): array
    {
        $db = $this->db;
        $uuid = $object->get('uuid');
        if ($uuid === null) {
            return [];
        }

        $type = $object->getShortTableName();
        $res = $db->fetchAll(
            $db->select()->from(['f' => "icinga_{$type}_property"], [
                'f.property_uuid',
            ])->join(['df' => 'director_property'], 'df.uuid = f.property_uuid', [])
                ->where("{$type}_uuid = ?", $uuid)
                ->order('key_name ASC')
        );

        if (empty($res)) {
            return [];
        }

        foreach ($res as $key => $property) {
            $property->property_uuid = Uuid::fromBytes($property->property_uuid)->toString();
            $res[$key] = $property;
        }

        return $res;
    }
}
