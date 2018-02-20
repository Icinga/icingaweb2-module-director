<?php

namespace Icinga\Module\Director\ProvidedHook\Monitoring;

use Exception;
use Icinga\Application\Config;
use Icinga\Module\Director\Db;
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
            return array();
        }
    }

    protected function getThem(Service $service)
    {
        $actions = array();
        $db = $this->db();
        if (! $db) {
            return array();
        }

        if (Util::hasPermission('director/inspect')) {
            $actions['Inspect'] = Url::fromPath(
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
