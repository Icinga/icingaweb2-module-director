<?php

namespace Icinga\Module\Director\ProvidedHook\Monitoring;

use Icinga\Application\Config;
use Icinga\Module\Director\Db;
use Icinga\Module\Director\Objects\IcingaHost;
use Icinga\Module\Monitoring\Hook\HostActionsHook;
use Icinga\Module\Monitoring\Object\Host;
use Icinga\Web\Url;

class HostActions extends HostActionsHook
{
    public function getActionsForHost(Host $host)
    {
        $db = $this->db();
        if (! $db) {
            return array();
        }

        if (IcingaHost::exists($host->host_name, $db)) {
            return array(
                'Modify' => Url::fromPath(
                    'director/host/edit',
                    array('name' => $host->host_name)
                ),
                'Inspect' => Url::fromPath(
                    'director/inspect/object',
                    array('type' => 'host', 'plural' => 'hosts', 'name' => $host->host_name)
                )
            );
        } else {
            return array();
        }
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
