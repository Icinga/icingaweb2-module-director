<?php

namespace Icinga\Module\Director\Data;

use gipfl\IcingaWeb2\Table\QueryBasedTable;
use gipfl\ZfDb\Select;
use Icinga\Authentication\Auth;
use Icinga\Data\SimpleQuery;
use Icinga\Module\Director\Db;
use Icinga\Module\Director\Db\AppliedServiceSetLoader;
use Icinga\Module\Director\Objects\IcingaHost;
use Icinga\Module\Director\Objects\IcingaService;
use Icinga\Module\Director\Objects\IcingaServiceSet;
use Icinga\Module\Director\Repository\IcingaTemplateRepository;
use Icinga\Module\Director\Web\Table\IcingaHostAppliedServicesTable;
use Icinga\Module\Director\Web\Table\IcingaServiceSetServiceTable;
use Icinga\Module\Director\Web\Table\ObjectsTableService;
use Ramsey\Uuid\Uuid;
use RuntimeException;
use Zend_Db_Select;

class HostServiceLoader
{
    /** @var Db */
    protected $connection;

    /** @var \Zend_Db_Adapter_Abstract */
    protected $db;

    /** @var Auth */
    protected $auth;

    /** @var bool */
    protected $resolveHostServices = false;

    /** @var bool */
    protected $resolveObjects = false;

    public function __construct(Db $connection, Auth $auth)
    {
        $this->connection = $connection;
        $this->db = $connection->getDbAdapter();
        $this->auth = $auth;
    }

    public function fetchServicesForHost(IcingaHost $host)
    {
        $table = (new ObjectsTableService($this->connection, $this->auth))
            ->setHost($host);
        $services = $this->fetchServicesForTable($table);
        if ($this->resolveHostServices) {
            foreach ($this->fetchAllServicesForHost($host) as $service) {
                $services[] = $service;
            }
        }

        return $services;
    }

    public function resolveHostServices($enable = true)
    {
        $this->resolveHostServices = $enable;
        return $this;
    }

    public function resolveObjects($resolve = true)
    {
        $this->resolveObjects = $resolve;
        return $this;
    }

    protected function fetchAllServicesForHost(IcingaHost $host)
    {
        $services = [];
        /** @var IcingaHost[] $parents */
        $parents = IcingaTemplateRepository::instanceByObject($host)->getTemplatesFor($host, true);
        foreach ($parents as $parent) {
            $table = (new ObjectsTableService($this->connection, $this->auth))
                ->setHost($parent)
                ->setInheritedBy($host);
            foreach ($this->fetchServicesForTable($table) as $service) {
                $services[] = $service;
            }
        }

        foreach ($this->getHostServiceSetTables($host) as $table) {
            foreach ($this->fetchServicesForTable($table) as $service) {
                $services[] = $service;
            }
        }
        foreach ($parents as $parent) {
            foreach ($this->getHostServiceSetTables($parent, $host) as $table) {
                foreach ($this->fetchServicesForTable($table) as $service) {
                    $services[] = $service;
                }
            }
        }

        $appliedSets = AppliedServiceSetLoader::fetchForHost($host);
        foreach ($appliedSets as $set) {
            $table = IcingaServiceSetServiceTable::load($set)
                // ->setHost($host)
                ->setAffectedHost($host);
            foreach ($this->fetchServicesForTable($table) as $service) {
                $services[] = $service;
            }
        }

        $table = IcingaHostAppliedServicesTable::load($host);
        foreach ($this->fetchServicesForTable($table) as $service) {
            $services[] = $service;
        }

        return $services;
    }

    /**
     * Duplicates Logic in HostController
     *
     * @param IcingaHost $host
     * @param IcingaHost|null $affectedHost
     * @return IcingaServiceSetServiceTable[]
     */
    protected function getHostServiceSetTables(IcingaHost $host, IcingaHost $affectedHost = null)
    {
        $tables = [];
        $db = $this->connection;
        if ($affectedHost === null) {
            $affectedHost = $host;
        }
        if ($host->get('id') === null) {
            return $tables;
        }

        $query = $db->getDbAdapter()->select()
            ->from(['ss' => 'icinga_service_set'], 'ss.*')
            ->join(['hsi' => 'icinga_service_set_inheritance'], 'hsi.parent_service_set_id = ss.id', [])
            ->join(['hs' => 'icinga_service_set'], 'hs.id = hsi.service_set_id', [])
            ->where('hs.host_id = ?', $host->get('id'));

        $sets = IcingaServiceSet::loadAll($db, $query, 'object_name');
        /** @var IcingaServiceSet $set*/
        foreach ($sets as $name => $set) {
            $tables[] = IcingaServiceSetServiceTable::load($set)
                ->setHost($host)
                ->setAffectedHost($affectedHost);
        }

        return $tables;
    }

    protected function fetchServicesForTable(QueryBasedTable $table)
    {
        $query = $table->getQuery();
        if ($query instanceof Select || $query instanceof Zend_Db_Select) {
            // What about SimpleQuery? IcingaHostAppliedServicesTable with branch in place?
            $query->reset(Select::LIMIT_COUNT);
            $query->reset(Select::LIMIT_OFFSET);
            $rows = $this->db->fetchAll($query);
        } elseif ($query instanceof SimpleQuery) {
            $rows = $query->fetchAll();
        } else {
            throw new RuntimeException('Table query needs to be either a Select or a SimpleQuery instance');
        }
        $services = [];
        foreach ($rows as $row) {
            $service = IcingaService::loadWithUniqueId(Uuid::fromBytes($row->uuid), $this->connection);
            if ($this->resolveObjects) {
                $service = $service::fromPlainObject($service->toPlainObject(true), $this->connection);
            }
            $services[] = $service;
        }

        return $services;
    }
}
