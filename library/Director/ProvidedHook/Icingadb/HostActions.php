<?php

namespace Icinga\Module\Director\ProvidedHook\Icingadb;

use Exception;
use Icinga\Application\Config;
use Icinga\Authentication\Auth;
use Icinga\Module\Director\Db;
use Icinga\Module\Director\Backend;
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
            return array();
        }
    }

    protected function getThem(Host $host): array
    {
        $actions = array();
        $db = $this->db();
        if (! $db) {
            return $actions;
        }
        $hostname = $host->name;
        if (Util::hasPermission('director/inspect')) {
            $actions[mt('director', 'Inspect')] = Url::fromPath(
                'director/inspect/object',
                array('type' => 'host', 'plural' => 'hosts', 'name' => $hostname)
            );
        }

        $allowEdit = false;
        if (Util::hasPermission('director/hosts') && IcingaHost::exists($hostname, $db)) {
            $allowEdit = true;
        }
        $auth = Auth::getInstance();
        if (Util::hasPermission('director/monitoring/hosts')) {
            $backend = new Backend(Backend::ICINGADB);
            if ($backend->isAvailable() && $backend->authCanEditHost($auth, $hostname)) {
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
