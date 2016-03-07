<?php

namespace Icinga\Module\Director;

use Icinga\Application\Config;
use Icinga\Exception\ProgrammingError;
use Icinga\Module\Director\Objects\IcingaApiUser;
use Icinga\Module\Director\Core\CoreApi;
use Icinga\Module\Director\Core\RestApiClient;
use Icinga\Module\Director\Db;

class KickstartHelper
{
    protected $db;

    protected $apiUser;

    protected $config = array(
        'endpoint' => null,
        'host'     => null,
        'port'     => null,
        'username' => null,
        'password' => null,
    );

    public function __construct(Db $db)
    {
        $this->db = $db;
    }

    public function isConfigured()
    {
        $config = $this->fetchConfigFileSection();
        return array_key_exists('endpoint', $config)
            && array_key_exists('username', $config);
    }

    public function loadConfigFromFile()
    {
        return $this->setConfig($this->fetchConfigFileSection());
    }

    protected function fetchConfigFileSection()
    {
        return Config::module('director', 'kickstart')
            ->getSection('config')
            ->toArray();
    }

    public function setConfig($config)
    {
        foreach ($config as $key => $value) {
            if ($value === '') {
                continue;
            }

            if (! array_key_exists($key, $this->config)) {
                throw new ProgrammingError(
                    '"%s" is not a valid config setting for the kickstart helper',
                    $key
                );
            }

            $this->config[$key] = $value;
        }

        return $this;
    }

    public function isRequired()
    {
        $stats = $this->db->getObjectSummary();
        return (int) $stats['apiuser']->cnt_total === 0;
    }

    protected function getValue($key, $default = null)
    {
        if ($this->config[$key] === null) {
            return $default;
        } else {
            return $this->config[$key];
        }
    }

    public function run()
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
        $imports = array();
        $objects = array();

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

    protected function getHost()
    {
        return $this->getValue('host', $this->getValue('endpoint'));
    }

    protected function getPort()
    {
        return (int) $this->getValue('port', 5665);
    }

    protected function api()
    {
        $client = new RestApiClient(
            $this->getHost(),
            $this->getPort()
        );

        $apiuser = $this->apiUser();
        $client->setCredentials($apiuser->object_name, $apiuser->password);

        $api = new CoreApi($client);
        return $api;
    }
}
