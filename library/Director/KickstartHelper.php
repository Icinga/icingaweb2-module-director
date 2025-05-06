<?php

namespace Icinga\Module\Director;

use Exception;
use Icinga\Application\Config;
use Icinga\Exception\ConfigurationError;
use Icinga\Exception\ProgrammingError;
use Icinga\Module\Director\Exception\NestingError;
use Icinga\Module\Director\Objects\IcingaApiUser;
use Icinga\Module\Director\Objects\IcingaCommand;
use Icinga\Module\Director\Objects\IcingaEndpoint;
use Icinga\Module\Director\Objects\IcingaObject;
use Icinga\Module\Director\Objects\IcingaZone;
use Icinga\Module\Director\Core\CoreApi;
use Icinga\Module\Director\Core\RestApiClient;
use RuntimeException;

class KickstartHelper
{
    /** @var Db */
    protected $db;

    /** @var CoreApi */
    protected $api;

    /** @var IcingaApiUser */
    protected $apiUser;

    /** @var  IcingaEndpoint */
    protected $deploymentEndpoint;

    /** @var  IcingaEndpoint[] */
    protected $loadedEndpoints;

    /** @var  IcingaEndpoint[] */
    protected $removeEndpoints;

    /** @var  IcingaZone[] */
    protected $loadedZones;

    /** @var  IcingaZone[] */
    protected $removeZones;

    /** @var  IcingaCommand[] */
    protected $loadedCommands;

    /** @var  IcingaCommand[] */
    protected $removeCommands;

    protected $config = [
        'endpoint' => null,
        'host'     => null,
        'port'     => null,
        'username' => null,
        'password' => null,
    ];

    /**
     * KickstartHelper constructor.
     * @param Db $db
     */
    public function __construct(Db $db)
    {
        $this->db = $db;
    }

    /**
     * Trigger a complete kickstart run
     */
    public function run()
    {
        $this->fetchEndpoints()
            ->reconnectToDeploymentEndpoint()
            ->fetchZones()
            ->fetchCommands()
            ->storeZones()
            ->storeEndpoints()
            ->storeCommands()
            ->removeEndpoints()
            ->removeZones()
            ->removeCommands();

        $this->apiUser()->store();
    }

    /**
     * @return bool
     */
    public function isConfigured()
    {
        $config = $this->fetchConfigFileSection();
        return array_key_exists('endpoint', $config)
            && array_key_exists('username', $config);
    }

    /**
     * @return KickstartHelper
     * @throws ProgrammingError
     */
    public function loadConfigFromFile()
    {
        return $this->setConfig($this->fetchConfigFileSection());
    }

    /**
     * @return array
     */
    protected function fetchConfigFileSection()
    {
        return Config::module('director', 'kickstart')
            ->getSection('config')
            ->toArray();
    }

    /**
     * @param  array $config
     * @return $this
     * @throws ProgrammingError
     */
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

    /**
     * @return bool
     */
    public function isRequired()
    {
        $stats = $this->db->getObjectSummary();
        return (int) $stats['apiuser']->cnt_total === 0;
    }

    /**
     * @param  $key
     * @param  mixed $default
     * @return mixed
     */
    protected function getValue($key, $default = null)
    {
        if ($this->config[$key] === null) {
            return $default;
        }

        return $this->config[$key];
    }

    /**
     * @return IcingaApiUser
     * @throws \Icinga\Exception\NotFoundError
     */
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

    /**
     * @param IcingaObject[] $objects
     * @return IcingaObject[]
     * @throws NestingError
     */
    protected function sortByParent(array $objects)
    {
        $sorted = array();

        $cnt = 0;
        while (! empty($objects)) {
            $cnt++;
            if ($cnt > 20) {
                $this->throwObjectLoop($objects);
            }

            $unset = array();
            foreach ($objects as $key => $object) {
                $parentName = $object->get('parent');
                if ($parentName === null || array_key_exists($parentName, $sorted)) {
                    $sorted[$object->getObjectName()] = $object;
                    $unset[] = $key;
                }
            }

            foreach ($unset as $key) {
                unset($objects[$key]);
            }
        }

        return $sorted;
    }

    /**
     * @param IcingaObject[] $objects
     * @throws NestingError
     */
    protected function throwObjectLoop(array $objects)
    {
        $names = array();
        if (empty($objects)) {
            $class = 'Nothing';
        } else {
            $class = explode('/\\/', get_class(current($objects)))[0];
        }

        foreach ($objects as $object) {
            $names[] = $object->getObjectName();
        }

        throw new NestingError(
            'Loop detected while resolving %s: %s',
            $class,
            implode(', ', $names)
        );
    }

    /**
     * @return $this
     * @throws NestingError
     * @throws \Icinga\Exception\NotFoundError
     */
    protected function fetchZones()
    {
        $db = $this->db;
        $this->loadedZones = $this->sortByParent(
            $this->api()->setDb($db)->getZoneObjects()
        );

        return $this;
    }

    /**
     * @return $this
     * @throws \Icinga\Module\Director\Exception\DuplicateKeyException
     * @throws \Icinga\Exception\NotFoundError
     */
    protected function storeZones()
    {
        $db = $this->db;
        $existing = IcingaObject::loadAllExternalObjectsByType('zone', $db);

        foreach ($this->loadedZones as $name => $object) {
            if (array_key_exists($name, $existing)) {
                $object = $existing[$name]->replaceWith($object);
                unset($existing[$name]);
            }

            $object->store();
        }

        $this->removeZones = $existing;

        return $this;
    }

    /**
     * @return $this
     */
    protected function removeZones()
    {
        return $this->removeObjects($this->removeZones, 'External Zone');
    }

    /**
     * @return $this
     * @throws \Icinga\Module\Director\Exception\DuplicateKeyException
     * @throws \Icinga\Exception\NotFoundError
     */
    protected function fetchEndpoints()
    {
        $db = $this->db;
        $this->loadedEndpoints = $this->api()->setDb($db)->getEndpointObjects();

        $master = $this->getValue('endpoint');
        if (array_key_exists($master, $this->loadedEndpoints)) {
            $apiuser = $this->apiUser();
            $apiuser->store();
            $object = $this->loadedEndpoints[$master];
            $object->apiuser = $apiuser->object_name;
            $this->deploymentEndpoint = $object;
        }

        return $this;
    }

    /**
     * @return $this
     * @throws \Icinga\Exception\NotFoundError
     * @throws \Icinga\Module\Director\Exception\DuplicateKeyException
     */
    protected function storeEndpoints()
    {
        $db = $this->db;
        $existing = IcingaObject::loadAllExternalObjectsByType('endpoint', $db);

        foreach ($this->loadedEndpoints as $name => $object) {
            if (array_key_exists($name, $existing)) {
                $object = $existing[$name]->replaceWith($object);
                unset($existing[$name]);
            }

            $object->store();
        }

        $this->removeEndpoints = $existing;

        $db->settings()->master_zone = $this->deploymentEndpoint->zone;

        return $this;
    }

    /**
     * @return $this
     */
    protected function removeEndpoints()
    {
        return $this->removeObjects($this->removeEndpoints, 'External Endpoint');
    }

    /**
     * @return $this
     * @throws ConfigurationError
     */
    protected function reconnectToDeploymentEndpoint()
    {
        $master = $this->getValue('endpoint');

        if (! $this->deploymentEndpoint) {
            throw new ConfigurationError(
                'I found no Endpoint object called "%s" on %s:%d',
                $master,
                $this->getHost(),
                $this->getPort()
            );
        }

        $ep = $this->deploymentEndpoint;

        $epHost = $ep->get('host');
        if (! $epHost) {
            $epHost = $ep->getObjectName();
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

    /**
     * @return $this
     * @throws \Icinga\Exception\NotFoundError
     */
    protected function fetchCommands()
    {
        $api = $this->api()->setDb($this->db);
        $this->loadedCommands = array_merge(
            $api->getSpecificCommandObjects('Check'),
            $api->getSpecificCommandObjects('Notification'),
            $api->getSpecificCommandObjects('Event')
        );

        return $this;
    }

    /**
     * @return $this
     * @throws \Icinga\Exception\NotFoundError
     * @throws \Icinga\Module\Director\Exception\DuplicateKeyException
     */
    protected function storeCommands()
    {
        $db = $this->db;
        $existing = IcingaObject::loadAllExternalObjectsByType('command', $db);

        foreach ($this->loadedCommands as $name => $object) {
            if (array_key_exists($name, $existing)) {
                $object = $existing[$name]->replaceWith($object);
                unset($existing[$name]);
            }

            $object->store();
        }

        $this->removeCommands = $existing;

        return $this;
    }

    /**
     * @return $this
     */
    protected function removeCommands()
    {
        return $this->removeObjects($this->removeCommands, 'External Command');
    }

    protected function removeObjects(array $objects, $typeName)
    {
        foreach ($objects as $object) {
            try {
                $object->delete();
            } catch (Exception $e) {
                throw new RuntimeException(sprintf(
                    "Failed to remove %s '%s', it's eventually still in use",
                    $typeName,
                    $object->getObjectName()
                ), 0, $e);
            }
        }

        return $this;
    }

    /**
     * @param Db $db
     * @return $this
     */
    public function setDb(Db $db)
    {
        $this->db = $db;
        return $this;
    }

    /**
     * @return string
     */
    protected function getHost()
    {
        return $this->getValue('host', $this->getValue('endpoint'));
    }

    /**
     * @return int
     */
    protected function getPort()
    {
        return (int) $this->getValue('port', 5665);
    }

    /**
     * @return CoreApi
     * @throws \Icinga\Exception\NotFoundError
     */
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
        $api->setDb($this->db);

        return $api;
    }

    /**
     * @return CoreApi
     * @throws \Icinga\Exception\NotFoundError
     */
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
        $api->setDb($this->db);

        return $api;
    }

    /**
     * @return CoreApi
     * @throws \Icinga\Exception\NotFoundError
     */
    protected function switchToDeploymentApi()
    {
        return $this->api = $this->getDeploymentApi();
    }

    /**
     * @return CoreApi
     * @throws \Icinga\Exception\NotFoundError
     */
    protected function api()
    {
        if ($this->api === null) {
            $this->api = $this->getConfiguredApi();
        }

        return $this->api;
    }
}
