<?php

namespace Icinga\Module\Director\ProvidedHook\Monitoring;

use Exception;
use Icinga\Application\Config;
use Icinga\Authentication\Auth;
use Icinga\Module\Director\Auth\Permission;
use Icinga\Module\Director\Db;
use Icinga\Module\Director\Integration\MonitoringModule\Monitoring;
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
        $serviceName = $service->service_description;
        if (Util::hasPermission(Permission::INSPECT)) {
            $actions[mt('director', 'Inspect')] = Url::fromPath('director/inspect/object', [
                'type'   => 'service',
                'plural' => 'services',
                'name'   => sprintf(
                    '%s!%s',
                    $hostname,
                    $serviceName
                )
            ]);
        }

        $title = null;
        if (Util::hasPermission(Permission::HOSTS)) {
            $title = mt('director', 'Modify');
        } elseif (Util::hasPermission(Permission::MONITORING_SERVICES)) {
            if ((new Monitoring(Auth::getInstance()))->canModifyService($hostname, $serviceName)) {
                $title = mt('director', 'Modify');
            }
        } elseif (Util::hasPermission(Permission::MONITORING_SERVICES_RO)) {
            $title = mt('director', 'Configuration');
        }

        if ($title && IcingaHost::exists($hostname, $db)) {
            $actions[$title] = Url::fromPath('director/host/findservice', [
                'name'    => $hostname,
                'service' => $serviceName
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
