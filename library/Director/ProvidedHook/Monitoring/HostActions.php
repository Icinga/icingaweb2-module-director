<?php

namespace Icinga\Module\Director\ProvidedHook\Monitoring;

use Exception;
use Icinga\Application\Config;
use Icinga\Authentication\Auth;
use Icinga\Module\Director\Db;
use Icinga\Module\Director\Monitoring;
use Icinga\Module\Director\Objects\IcingaHost;
use Icinga\Module\Director\Util;
use Icinga\Module\Monitoring\Hook\HostActionsHook;
use Icinga\Module\Monitoring\Object\Host;
use Icinga\Web\Url;

class HostActions extends HostActionsHook
{
    public function getActionsForHost(Host $host)
    {
        try {
            return $this->getThem($host);
        } catch (Exception $e) {
            return array();
        }
    }

    protected function getThem(Host $host)
    {
        $actions = array();
        $db = $this->db();
        if (! $db) {
            return $actions;
        }
        $hostname = $host->host_name;
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
            $monitoring = new Monitoring();
            if ($monitoring->authCanEditHost($auth, $hostname)) {
                $allowEdit = IcingaHost::exists($hostname, $db);
            }
        }

        if ($allowEdit) {
            $actions[mt('director', 'Modify')] = Url::fromPath(
                'director/host/edit',
                array('name' => $hostname)
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
