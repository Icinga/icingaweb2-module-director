<?php

namespace Icinga\Module\Director\ProvidedHook\Icingadb;

use Exception;
use Icinga\Application\Config;
use Icinga\Module\Director\Auth\Permission;
use Icinga\Module\Director\Db;
use Icinga\Module\Director\Integration\Icingadb\IcingadbBackend;
use Icinga\Module\Director\Objects\IcingaHost;
use Icinga\Module\Director\Util;
use Icinga\Module\Icingadb\Hook\HostActionsHook;
use Icinga\Module\Icingadb\Model\Host;
use ipl\Web\Url;
use ipl\Web\Widget\Link;

class HostActions extends HostActionsHook
{
    public function getActionsForObject(Host $host): array
    {
        try {
            return $this->getThem($host);
        } catch (Exception $e) {
            return [];
        }
    }

    protected function getThem(Host $host): array
    {
        $actions = [];
        $db = $this->db();
        if (! $db) {
            return $actions;
        }
        $hostname = $host->name;
        if (Util::hasPermission(Permission::INSPECT)) {
            $actions[] = new Link(
                mt('director', 'Inspect'),
                Url::fromPath(
                    'director/inspect/object',
                    ['type' => 'host', 'plural' => 'hosts', 'name' => $hostname]
                )
            );
        }

        $allowEdit = false;
        if (Util::hasPermission(Permission::HOSTS) && IcingaHost::exists($hostname, $db)) {
            $allowEdit = true;
        }
        if (Util::hasPermission(Permission::ICINGADB_HOSTS)) {
            if ((new IcingadbBackend())->canModifyHost($hostname)) {
                $allowEdit = IcingaHost::exists($hostname, $db);
            }
        }

        if ($allowEdit) {
            $label = mt('director', 'Modify');
            $actions[] = new Link(
                $label,
                Url::fromPath('director/host/edit', [
                    'name'    => $hostname
                ])
            );
        }

        return $actions;
    }

    protected function db()
    {
        $resourceName = Config::module('director')->get('db', 'resource');
        if (! $resourceName) {
            return false;
        }

        return Db::fromResourceName($resourceName);
    }
}
