<?php

namespace Icinga\Module\Director;

use Icinga\Application\Config;
use Icinga\Module\Director\Objects\IcingaApiUser;
use Icinga\Module\Director\Core\CoreApi;
use Icinga\Module\Director\Core\RestApiClient;
use Icinga\Module\Director\Db;

class KickstartHelper
{
    protected $db;

    public function __construct(Db $db)
    {
        $this->db = $db;
    }

    public function isConfigured()
    {
        $file = Config::module('director', 'kickstart')->getConfigFile();
        return is_readable($file);
    }

    public function loadConfigFile()
    {
    }

    public function setConfig($config)
    {
    }

    public function isRequired()
    {
        $stats = $this->db->getObjectSummary();
        return (int) $stats['apiuser']->cnt_total === 0;
    }


    protected function run()
    {
        $this->importZones()
             ->importEndpoints()
             ->importCommands();

        $this->apiUser()->store();

    }

    protected function apiUser()
    {
        if ($this->apiUser === null) {
            $this->apiUser = IcingaApiUser::create(array(
                'object_name' => $this->getValue('username'),
                'object_type' => 'external_object',
                'password'    => $this->getValue('password')
            ), $this->db);
        }

        return $this->apiUser;
    }

    protected function importZones()
    {
        $db = $this->db;
        foreach ($this->api()->setDb($db)->getZoneObjects() as $object) {
            if (! $object::exists($object->object_name, $db)) {
                $object->store();
            }
        }

        return $this;
    }

    protected function importEndpoints()
    {
        $db = $this->db;
        $master = $this->getValue('endpoint');

        foreach ($this->api()->setDb($db)->getEndpointObjects() as $object) {

            if ($object->object_name === $master) {
                $apiuser = $this->apiUser();
                $apiuser->store();
                $object->apiuser = $apiuser->object_name;
            }

            if (! $object::exists($object->object_name, $db)) {
                $object->store();
            }
        }

        return $this;
    }

    protected function importCommands()
    {
        $db = $this->db;
        foreach ($this->api()->setDb($db)->getCheckCommandObjects() as $object) {
            if (! $object::exists($object->object_name, $db)) {
                $object->store();
            }
        }

        return $this;
    }

    public function setDb($db)
    {
        $this->db = $db;
        if ($this->object !== null) {
            $this->object->setConnection($db);
        }

        return $this;
    }

    protected function api()
    {
        $client = new RestApiClient(
            $this->getValue('host'),
            $this->getValue('port')
        );

        $apiuser = $this->apiUser();
        $client->setCredentials($apiuser->object_name, $apiuser->password);

        $api = new CoreApi($client);
        return $api;
    }
}
