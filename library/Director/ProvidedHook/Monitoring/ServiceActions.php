<?php

namespace Icinga\Module\Director\ProvidedHook\Monitoring;

use Icinga\Application\Config;
use Icinga\Module\Director\Db;
use Icinga\Module\Director\Objects\IcingaHost;
use Icinga\Module\Monitoring\Web\Hook\ServiceActionsHook;
use Icinga\Module\Monitoring\Object\Service;
use Icinga\Web\Url;

class ServiceActions extends ServiceActionsHook
{
    public function getActionsForService(Service $service)
    {
        $db = $this->db();
        if (IcingaHost::exists($service->host_name, $db)) {
            return array(
                'Inspect' => Url::fromPath(
                    'director/inspect/object',
                    array(
                        'type'   => 'service',
                        'plural' => 'services',
                        'name'   => sprintf(
                            '%s!%s',
                            $service->host_name,
                            $service->service_description
                        )
                    )
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
