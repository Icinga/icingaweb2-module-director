<?php

namespace Icinga\Module\Director\Web;

use Icinga\Application\Config;
use Icinga\Module\Director\Db;
use Icinga\Module\Director\Objects\IcingaHost;
use Icinga\Module\Monitoring\Web\Hook\HostActionsHook;
use Icinga\Module\Monitoring\Object\Host;
use Icinga\Web\Url;

class HostActions extends HostActionsHook
{
    public function getActionsForHost(Host $host)
    {
        $db = $this->db();
        if (IcingaHost::exists($host->host_name, $db)) {
            return array(
                'Modify' => Url::fromPath(
                    'director/host/edit',
                    array('name' => $host->host_name)
                )
            );
        } else {
            return array();
        }
    }

    protected function db()
    {
        return Db::fromResourceName(Config::module('director')->get('db', 'resource'));
    }
}
