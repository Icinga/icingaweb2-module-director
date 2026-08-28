<?php

namespace Icinga\Module\Director\Data\Db;

use Icinga\Module\Director\Db;
use Icinga\Module\Director\Db\DbUtil;
use Icinga\Module\Director\Objects\IcingaService;
use Icinga\Module\Director\Objects\IcingaServiceSet;
use Ramsey\Uuid\Uuid;

class ServiceSetQueryBuilder
{
    public const TABLE = 'icinga_service';
    public const SET_TABLE = 'icinga_service_set';

    /** @var Db */
    protected $connection;

    /** @var \Zend_Db_Adapter_Abstract */
    protected $db;

    protected $searchColumns = [];

    public function __construct(Db $connection)
    {
        $this->connection = $connection;
        $this->db = $connection->getDbAdapter();
    }

    /**
     * @return \Zend_Db_Select
     */
    public function selectServicesForSet(IcingaServiceSet $set)
    {
        return $this->selectServices($set)->columns($this->getColumns());
    }

    protected function selectServices(IcingaServiceSet $set)
    {
        return $this->db
            ->select()
            ->from(['o' => self::TABLE], [])
            ->joinLeft(['os' => self::SET_TABLE], 'os.id = o.service_set_id', [])
            ->where('os.uuid = ?', $this->connection->quoteBinary($set->getUniqueId()->getBytes()));
    }

    protected static function resetQueryProperties(\Zend_Db_Select $query)
    {
        // TODO: Keep existing UUID, becomes important when using this for other tables too (w/o UNION)
        // $columns = $query->getPart($query::COLUMNS);
        $query->reset($query::COLUMNS);
        $query->columns('uuid');
        return $query;
    }

    public function fetchServicesWithQuery(\Zend_Db_Select $query)
    {
        static::resetQueryProperties($query);
        $db = $this->connection->getDbAdapter();
        $uuids = $db->fetchCol($query);

        $services = [];
        foreach ($uuids as $uuid) {
            $service = IcingaService::loadWithUniqueId(Uuid::fromBytes(DbUtil::binaryResult($uuid)), $this->connection);
            $service->set('service_set', null); // TODO: CHECK THIS!!!!

            $services[$service->getObjectName()] = $service;
        }

        return $services;
    }

    protected function getColumns()
    {
        return [
            'uuid'           => 'o.uuid',
            'id'             => 'o.id',
            'service_set'    => 'os.object_name',
            'service'        => 'o.object_name',
            'disabled'       => 'o.disabled',
            'object_type'    => 'o.object_type',
            'blacklisted'    => "('n')",
        ];
    }
}
