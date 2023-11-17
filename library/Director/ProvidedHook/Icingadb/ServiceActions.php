<?php

namespace Icinga\Module\Director\ProvidedHook\Icingadb;

use Exception;
use Icinga\Application\Config;
use Icinga\Module\Director\Backend\MonitorBackendIcingadb;
use Icinga\Module\Director\Db;
use Icinga\Module\Director\Objects\IcingaHost;
use Icinga\Module\Director\Util;
use Icinga\Module\Icingadb\Hook\ServiceActionsHook;
use Icinga\Module\Icingadb\Model\Service;
use ipl\Web\Url;
use ipl\Web\Widget\Link;

class ServiceActions extends ServiceActionsHook
{
    public function getActionsForObject(Service $service): array
    {
        try {
            return $this->getThem($service);
        } catch (Exception $e) {
            die($e);
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

        $hostname = $service->host->name;
        $serviceName = $service->name;
        if (Util::hasPermission('director/inspect')) {
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
        if (Util::hasPermission('director/hosts')) {
            $title = mt('director', 'Modify');
        } elseif (Util::hasPermission('director/monitoring/services')) {
            $backend = new MonitorBackendIcingadb();
            if ($backend->isAvailable()
                && $backend->canModifyService($hostname, $serviceName)
            ) {
                $title = mt('director', 'Modify');
            }
        } elseif (Util::hasPermission('director/monitoring/services-ro')) {
            $title = mt('director', 'Configuration');
        }

        if ($title && IcingaHost::exists($hostname, $db)) {
            $actions[] = new Link(
                $title,
                Url::fromPath('director/host/findservice', [
                    'name'    => $hostname,
                    'service' => $serviceName
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
