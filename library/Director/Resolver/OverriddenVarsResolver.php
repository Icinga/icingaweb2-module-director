<?php

namespace Icinga\Module\Director\Resolver;

use Icinga\Module\Director\Db;
use Icinga\Module\Director\Objects\IcingaHost;
use Icinga\Module\Director\Objects\IcingaService;
use Icinga\Util\Json;

class OverriddenVarsResolver
{
    /** @var \Zend_Db_Adapter_Abstract */
    protected $db;

    /** @var string */
    protected $varName;

    public function __construct(Db $connection)
    {
        $this->varName = $connection->settings()->override_services_varname;
        $this->db = $connection->getDbAdapter();
    }

    public function resolveFor(IcingaHost $host, IcingaService $service = null)
    {
        $parents = $host->listFlatResolvedImportNames();
        $query = $this->db->select()->from(['hv' => 'icinga_host_var'], [
            'host_name' => 'h.object_name',
            'varvalue'  => 'hv.varvalue',
        ])->join(
            ['h' => 'icinga_host'],
            'h.id = hv.host_id',
            []
        )->where('hv.varname = ?', $this->varName)->where('h.object_name IN (?)', $parents);
        $overrides = [];
        foreach ($this->db->fetchAll($query) as $row) {
            if ($row->varvalue === null) {
                continue;
            }
            foreach (Json::decode($row->varvalue) as $serviceName => $vars) {
                $overrides[$serviceName][$row->host_name] = $vars;
            }
        }

        if ($service) {
            $name = $service->getObjectName();
            if (isset($overrides[$name])) {
                return $overrides[$name];
            } else {
                return [];
            }
        }

        return $overrides;
    }
}
