<?php

namespace Icinga\Module\Director;

use Exception;
use Icinga\Application\Config;
use Icinga\Exception\ConfigurationError;
use Icinga\Exception\ProgrammingError;
use Icinga\Module\Director\Objects\IcingaApiUser;
use Icinga\Module\Director\Objects\IcingaEndpoint;
use Icinga\Module\Director\Objects\IcingaZone;
use Icinga\Module\Director\Core\CoreApi;
use Icinga\Module\Director\Core\RestApiClient;
use Icinga\Module\Director\Db;

class KickstartHelper
{
    protected $db;

    protected $api;

    protected $apiUser;

    protected $deploymentEndpoint;

    protected $loadedEndpoints;

    protected $loadedZones;

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
        $this->loadEndpoints()
             ->reconnectToDeploymentEndpoint()
             ->loadZones()
             ->storeZones()
             ->storeEndpoints()
             ->importCommands();

        $this->apiUser()->store();
    }

    protected function apiUser()
    {
        if ($this->apiUser === null) {

            $name = $this->getValue('username');

            $user = IcingaApiUser::create(array(
                'object_name' => $this->getValue('username'),
                'object_type' => 'external_object',
                'password'    => $this->getValue('password')
            ), $this->db);

            if (IcingaApiUser::exists($name, $this->db)) {
                $this->apiUser = IcingaApiUser::load($name, $this->db)->replaceWith($user);
            } else {
                $this->apiUser = $user;
            }

            $this->apiUser->store();
        }

        return $this->apiUser;
    }

    protected function loadZones()
    {
        $db = $this->db;
        $imports = array();
        $objects = array();
        $children = array();
        $root = array();

        foreach ($this->api()->setDb($db)->getZoneObjects() as $object) {
            if ($object->parent) {
                $children[$object->parent][$object->object_name] = $object;
            } else {
                $root[$object->object_name] = $object;
            }
        }

        foreach ($root as $name => $object) {
            $objects[$name] = $object;
        }

        $loop = 0;
        while (! empty($children)) {
            $loop++;
            $unset = array();
            foreach ($objects as $name => $object) {
                if (array_key_exists($name, $children)) {
                    foreach ($children[$name] as $object) {
                        $objects[$object->object_name] = $object;
                    }

                    unset($children[$name]);
                }
            }

            if ($loop > 20) {
                throw new ConfigurationError('Loop detected while importing zones');
            }
        }

        $this->loadedZones = $objects;

        return $this;
    }

    protected function storeZones()
    {
        $db = $this->db;
        $existing = $db->listExternal('zone');
        foreach ($this->loadedZones as $name => $zone) {
            if ($zone::exists($name, $db)) {
                $zone = $zone::load($name, $db)->replaceWith($zone);
            }
            $zone->store();
            unset($existing[$name]);
        }
        foreach ($existing as $name) {
            IcingaZone::load($name, $db)->delete();
        }

        return $this;
    }

    protected function loadEndpoints()
    {
        $db = $this->db;
        $master = $this->getValue('endpoint');

        $endpoints = array();
        foreach ($this->api()->setDb($db)->getEndpointObjects() as $object) {

            if ($object->object_name === $master) {
                $apiuser = $this->apiUser();
                $apiuser->store();
                $object->apiuser = $apiuser->object_name;
                $this->deploymentEndpoint = $object;
            }

            $endpoints[$object->object_name] = $object;
        }

        $this->loadedEndpoints = $endpoints;

        return $this;
    }

    protected function reconnectToDeploymentEndpoint()
    {
        $db = $this->db;
        $master = $this->getValue('endpoint');

        if (!$this->deploymentEndpoint) {
            throw new ConfigurationError(
                'I found no Endpoint object called "%s" on %s:%d',
                $master,
                $this->getHost(),
                $this->getPort()
            );
        }

        $ep = $this->deploymentEndpoint;

        $epHost = $ep->get('host');
        if (!$epHost) {
            $epHost = $ep->object_name;
        }

        try {
            $this->switchToDeploymentApi()->getStatus();
        } catch (Exception $e) {

            throw new ConfigurationError(
                'I was unable to re-establish a connection to the Endpoint "%s" (%s:%d).'
                . ' When reconnecting to the configured Endpoint (%s:%d) I get an error: %s'
                . ' Please re-check your Icinga 2 endpoint configuration',
                $master,
                $this->getHost(),
                $this->getPort(),
                $epHost,
                $ep->get('port'),
                $e->getMessage()
            );
        }

        return $this;
    }

    protected function storeEndpoints()
    {
        $db = $this->db;

        foreach ($this->loadedEndpoints as $name => $object) {
            if ($object::exists($object->object_name, $db)) {
                $object = $object::load($object->object_name, $db)->replaceWith($object);
            }

            $object->store();
        }

        $db->storeSetting('master_zone', $this->deploymentEndpoint->zone);

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

    protected function getDeploymentApi()
    {
        unset($this->api);
        $ep = $this->deploymentEndpoint;

        $epHost = $ep->get('host');
        if (!$epHost) {
            $epHost = $ep->object_name;
        }

        $client = new RestApiClient(
            $epHost,
            $ep->get('port')
        );

        $apiuser = $this->apiUser();
        $client->setCredentials($apiuser->object_name, $apiuser->password);

        $api = new CoreApi($client);
        return $api;
    }

    protected function getConfiguredApi()
    {
        unset($this->api);
        $client = new RestApiClient(
            $this->getHost(),
            $this->getPort()
        );

        $apiuser = $this->apiUser();
        $client->setCredentials($apiuser->object_name, $apiuser->password);

        $api = new CoreApi($client);
        return $api;
    }

    protected function switchToDeploymentApi()
    {
        return $this->api = $this->getDeploymentApi();
    }

    protected function api()
    {
        if ($this->api === null) {
            $this->api = $this->getConfiguredApi();
        }

        return $this->api;
    }
}
