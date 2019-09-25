<?php

namespace Icinga\Module\Director\ProvidedHook\Monitoring;

use Exception;
use Icinga\Application\Config;
use Icinga\Module\Director\Db;
use Icinga\Module\Director\Objects\IcingaHost;
use Icinga\Module\Director\Util;
use Icinga\Module\Monitoring\Hook\ServiceActionsHook;
use Icinga\Module\Monitoring\Object\Service;
use Icinga\Web\Url;

class ServiceActions extends ServiceActionsHook
{
    public function getActionsForService(Service $service)
    {
        try {
            return $this->getThem($service);
        } catch (Exception $e) {
            return [];
        }
    }

    /**
     * @param Service $service
     * @return array
     * @throws \Icinga\Exception\ProgrammingError
     */
    protected function getThem(Service $service)
    {
        $actions = [];
        $db = $this->db();
        if (! $db) {
            return [];
        }

        $hostname = $service->host_name;
        if (Util::hasPermission('director/inspect')) {
            $actions[mt('director', 'Inspect')] = Url::fromPath('director/inspect/object', [
                'type'   => 'service',
                'plural' => 'services',
                'name'   => sprintf(
                    '%s!%s',
                    $hostname,
                    $service->service_description
                )
            ]);
        }

        if (Util::hasPermission('director/hosts')) {
            $title = mt('director', 'Modify');
        } elseif (Util::hasPermission('director/monitoring/services-ro')) {
            $title = mt('director', 'Configuration');
        } else {
            return $actions;
        }

        if (IcingaHost::exists($hostname, $db)) {
            $actions[$title] = Url::fromPath('director/host/findservice', [
                'name'    => $hostname,
                'service' => $service->service_description
            ]);
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
