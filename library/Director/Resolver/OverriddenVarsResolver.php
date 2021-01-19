<?php

namespace Icinga\Module\Director\Resolver;

use Icinga\Module\Director\Core\Json;
use Icinga\Module\Director\Db;
use Icinga\Module\Director\Objects\IcingaHost;

class OverriddenVarsResolver
{
    /** @var \Zend_Db_Adapter_Abstract */
    protected $db;

    /** @var string */
    protected $overrideVarName;

    public function __construct(Db $connection)
    {
        $this->overrideVarName = $connection->settings()->get('override_services_varname');
        $this->db = $connection->getDbAdapter();
    }

    public function fetchForHost(IcingaHost $host)
    {
        $parents = $host->listFlatResolvedImportNames();
        $query = $this->db->select()
            ->from(['hv' => 'icinga_host_var'], [
                'host_name' => 'h.object_name',
                'varvalue'  => 'hv.varvalue',
            ])
            ->join(
                ['h' => 'icinga_host'],
                'h.id = hv.host_id',
                []
            )
            ->where('hv.varname = ?', $this->overrideVarName)
            ->where('h.object_name IN (?)', $parents);

        $overrides = [];
        foreach ($this->db->fetchAll($query) as $row) {
            if ($row->varvalue === null) {
                continue;
            }
            foreach (Json::decode($row->varvalue) as $serviceName => $vars) {
                $overrides[$serviceName][$row->host_name] = $vars;
            }
        }

        return $overrides;
    }

    public function fetchForServiceName(IcingaHost $host, $serviceName)
    {
        $overrides = $this->fetchForHost($host);
        if (isset($overrides[$serviceName])) {
            return $overrides[$serviceName];
        }

        return [];
    }

    public function fetchVarForServiceName(IcingaHost $host, $serviceName, $varName)
    {
        $overrides = $this->fetchForHost($host);
        if (isset($overrides[$serviceName][$varName])) {
            return $overrides[$serviceName][$varName];
        }

        return null;
    }
}
