<?php

namespace Icinga\Module\Director\Resolver;

use Icinga\Module\Director\Db;
use Icinga\Module\Director\Objects\IcingaService;

class HostServiceBlacklist
{
    /** @var Db */
    protected $db;

    protected $table = 'icinga_host_service_blacklist';

    protected $mappings;

    public function __construct(Db $db)
    {
        $this->db = $db;
    }

    protected function loadMappings()
    {
        $db = $this->db->getDbAdapter();
        $query = $db->select()->from(['hsb' => $this->table], [
            'host_name' => 'h.object_name',
            'service_id' => 'hsb.service_id'
        ])->join(
            ['h' => 'icinga_host'],
            'hsb.host_id = h.id',
            []
        );

        $result = [];
        foreach ($db->fetchAll($query) as $row) {
            if (array_key_exists($row->service_id, $result)) {
                $result[$row->service_id][] = $row->host_name;
            } else {
                $result[$row->service_id] = [$row->host_name];
            }
        }

        return $result;
    }

    public function preloadMappings()
    {
        $this->mappings = $this->loadMappings();

        return $this;
    }

    public function getBlacklistedHostnamesForService(IcingaService $service)
    {
        if ($this->mappings === null) {
            return $this->fetchMappingsForService($service);
        } else {
            return $this->getPreLoadedMappingsForService($service);
        }
    }

    public function fetchMappingsForService(IcingaService $service)
    {
        if (! $service->hasBeenLoadedFromDb() || $service->get('id') === null) {
            return [];
        }

        $db = $this->db->getDbAdapter();
        $query = $db->select()->from(['hsb' => $this->table], [
            'host_name' => 'h.object_name',
            'service_id' => 'hsb.service_id'
        ])->join(
            ['h' => 'icinga_host'],
            'hsb.host_id = h.id',
            []
        )->where('hsb.service_id = ?', $service->get('id'));

        return $db->fetchCol($query);
    }

    public function getPreLoadedMappingsForService(IcingaService $service)
    {
        if (
            $this->mappings !== null
            && array_key_exists($service->get('id'), $this->mappings)
        ) {
            return $this->mappings[$service->get('id')];
        }

        return [];
    }
}
