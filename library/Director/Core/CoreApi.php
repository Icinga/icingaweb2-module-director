<?php

namespace Icinga\Module\Director\Core;

use Exception;
use Icinga\Exception\NotFoundError;
use Icinga\Module\Director\Db;
use Icinga\Module\Director\Hook\DeploymentHook;
use Icinga\Module\Director\IcingaConfig\IcingaConfig;
use Icinga\Module\Director\Objects\IcingaObject;
use Icinga\Module\Director\Objects\IcingaCommand;
use Icinga\Module\Director\Objects\DirectorDeploymentLog;
use Icinga\Module\Director\Objects\IcingaZone;
use Icinga\Web\Hook;
use RuntimeException;

class CoreApi implements DeploymentApiInterface
{
    protected $client;

    protected $initialized = false;

    /** @var Db */
    protected $db;

    public function __construct(RestApiClient $client)
    {
        $this->client = $client;
    }

    // Todo: type
    public function setDb(Db $db)
    {
        $this->db = $db;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getVersion()
    {
        return $this->parseVersion($this->getRawVersion());
    }

    public function enableWorkaroundForConnectionIssues()
    {
        $version = $this->getVersion();

        if (version_compare($version, '2.8.2', '>=')
            && version_compare($version, '2.10.2', '<')
        ) {
            $this->client->disconnect();
            $this->client->setKeepAlive(false);
        }
    }

    /**
     * @return string|null
     */
    public function getRawVersion()
    {
        try {
            return $this->client()->get('')->getRaw('version');
        } catch (Exception $exception) {
            return null;
        }
    }

    /**
     * @param $version
     * @return string|null
     */
    protected function parseVersion($version)
    {
        if ($version === null) {
            return null;
        }

        if (preg_match('/^v?(\d\.\d+\.\d+)/', $version, $match)) {
            return $match[1];
        } else {
            return null;
        }
    }

    public function getObjects($pluralType, $attrs = array(), $ignorePackage = null)
    {
        $params = (object) [];
        if ($ignorePackage) {
            $params->filter = 'obj.package!="' . $ignorePackage . '"';
        }

        if (! empty($attrs)) {
            $params->attrs = $attrs;
        }

        return $this->client()->get(
            'objects/' . urlencode(strtolower($pluralType)),
            $params
        )->getResult('name');
    }

    public function onEvent($callback, $raw = false)
    {
        $this->client->onEvent($callback, $raw);

        return $this;
    }

    public function getObject($name, $pluraltype, $attrs = array())
    {
        $params = (object) array(
        );

        if (! empty($attrs)) {
            $params->attrs = $attrs;
        }
        $url = 'objects/' . urlencode(strtolower($pluraltype)) . '/' . rawurlencode($name) . '?all_joins=1';
        $res = $this->client()->get($url, $params)->getResult('name');

        // TODO: check key, throw
        return $res[$name];
    }

    public function getTicketSalt()
    {
        // TODO: api must not be the name!
        $api = $this->getObject('api', 'ApiListeners', array('ticket_salt'));
        if (isset($api->attrs->ticket_salt)) {
            return $api->attrs->ticket_salt;
        }

        return null;
    }

    public function checkHostNow($host)
    {
        $filter = 'host.name == "' . $host . '"';

        return $this->client()->post(
            'actions/reschedule-check?filter=' . rawurlencode($filter),
            (object) array(
                'type' => 'Host'
            )
        );
    }

    public function checkServiceNow($host, $service)
    {
        $filter = 'host.name == "' . $host . '" && service.name == "' . $service . '"';
        $this->client()->post(
            'actions/reschedule-check?filter=' . rawurlencode($filter),
            (object) array(
                'type' => 'Service'
            )
        );
    }

    public function acknowledgeHostProblem($host, $author, $comment)
    {
        $filter = 'host.name == "' . $host . '"';
        return $this->client()->post(
            'actions/acknowledge-problem?type=Host&filter=' . rawurlencode($filter),
            (object) array(
                'author'  => $author,
                'comment' => $comment
            )
        );
    }

    public function removeHostAcknowledgement($host)
    {
        $filter = 'host.name == "' . $host . '"';
        return $this->client()->post(
            'actions/remove-acknowledgement?type=Host&filter=' . rawurlencode($filter)
        );
    }

    public function reloadNow()
    {
        try {
            $this->client()->post('actions/restart-process');

            return true;
        } catch (Exception $e) {
            return $e->getMessage();
        }
    }

    public function getHostOutput($host)
    {
        try {
            $object = $this->getObject($host, 'hosts');
        } catch (Exception $e) {
            return 'Unable to fetch the requested object';
        }
        if (isset($object->attrs->last_check_result)) {
            return $object->attrs->last_check_result->output;
        } else {
            return '(no check result available)';
        }
    }

    public function checkHostAndWaitForResult($host, $timeout = 10)
    {
        $object = $this->getObject($host, 'hosts');
        if (isset($object->attrs->last_check_result)) {
            $oldOutput = $object->attrs->last_check_result->output;
        } else {
            $oldOutput = '';
        }

        $now = microtime(true);
        $this->checkHostNow($host);

        while (true) {
            try {
                $object = $this->getObject($host, 'hosts');
                if (isset($object->attrs->last_check_result)) {
                    $res = $object->attrs->last_check_result;
                    if ($res->execution_start > $now || $res->output !== $oldOutput) {
                        return $res;
                    }
                } else {
                    // no check result available
                }
            } catch (Exception $e) {
                // Unable to fetch the requested object
                throw new RuntimeException(sprintf(
                    'Unable to fetch the requested host "%s"',
                    $host
                ));
            }
            if (microtime(true) > ($now + $timeout)) {
                break;
            }

            usleep(50000);
        }

        return false;
    }

    public function checkServiceAndWaitForResult($host, $service, $timeout = 10)
    {
        $now = microtime(true);
        $this->checkServiceNow($host, $service);

        while (true) {
            try {
                $object = $this->getObject("$host!$service", 'services');
                if (isset($object->attrs->last_check_result)) {
                    $res = $object->attrs->last_check_result;
                    if ($res->execution_start > $now) {
                        return $res;
                    }
                } else {
                    // no check result available
                }
            } catch (Exception $e) {
                // Unable to fetch the requested object
                throw new RuntimeException(sprintf(
                    'Unable to fetch the requested service "%s" on "%s"',
                    $service,
                    $host
                ));
            }
            if (microtime(true) > ($now + $timeout)) {
                break;
            }

            usleep(150000);
        }

        return false;
    }

    public function getServiceOutput($host, $service)
    {
        try {
            $object = $this->getObject($host . '!' . $service, 'services');
        } catch (\Exception $e) {
            return 'Unable to fetch the requested object';
        }
        if (isset($object->attrs->last_check_result)) {
            return $object->attrs->last_check_result->output;
        } else {
            return '(no check result available)';
        }
    }

    public function supportsRuntimeCreationFor(IcingaObject $object)
    {
        $valid = array('host');
        return in_array($object->getShortTableName(), $valid);
    }

    protected function assertRuntimeCreationSupportFor(IcingaObject $object)
    {
        if (!$this->supportsRuntimeCreationFor($object)) {
            throw new RuntimeException(sprintf(
                'Object creation at runtime is not supported for "%s"',
                $object->getShortTableName()
            ));
        }
    }

    // Note: this is for testing purposes only, NOT production-ready
    public function createObjectAtRuntime(IcingaObject $object)
    {
        $this->assertRuntimeCreationSupportFor($object);

        $key = $object->getShortTableName();

        $command = sprintf(
            "f = function() {\n"
            . '  existing = get_%s("%s")'
            . "\n  if (existing) { return false }"
            . "\n%s\n}\nInternal.run_with_activation_context(f)\n",
            $key,
            $object->get('object_name'),
            (string) $object
        );

        return $this->runConsoleCommand($command)->getSingleResult();
    }

    public function getConstants()
    {
        $constants = array();
        $command = 'var constants = [];
for (k => v in globals) {
   if (typeof(v) in [String, Number, Boolean]) {
      res = { name = k, value = v }
      constants.add({name = k, value = v})
   }
};
constants
';

        foreach ($this->runConsoleCommand($command)->getSingleResult() as $row) {
            $constants[$row->name] = $row->value;
        }

        return $constants;
    }

    public function runConsoleCommand($command)
    {
        return $this->client()->post(
            'console/execute-script',
            array('command' => $command)
        );
    }

    public function getConstant($name)
    {
        $constants = $this->getConstants();
        if (array_key_exists($name, $constants)) {
            return $constants[$name];
        }

        return null;
    }

    public function getTypes()
    {
        return $this->client()->get('types')->getResult('name');
    }

    public function getType($type)
    {
        $res = $this->client()->get('types', array('name' => $type))->getResult('name');
        return $res[$type]; // TODO: error checking
    }

    public function getStatus()
    {
        return $this->client()->get('status')->getResult('name');
    }

    public function listObjects($type, $pluralType)
    {
        // TODO: more abstraction needed
        // TODO: autofetch and cache pluraltypes
        try {
            $result = $this->client()->get(
                'objects/' . $pluralType,
                array(
                    'attrs' => array('__name')
                )
            )->getResult('name');
        } catch (NotFoundError $e) {
            $result = [];
        }

        return array_keys($result);
    }

    public function getPackages()
    {
        return $this->client()->get('config/packages')->getResult('name');
    }

    public function getActiveStageName()
    {
        return current($this->listPackageStages($this->getPackageName(), true));
    }

    protected function getPackageName()
    {
        return $this->db->settings()->get('icinga_package_name');
    }

    public function getActiveChecksum(Db $conn)
    {
        $db = $conn->getDbAdapter();
        $stage = $this->getActiveStageName();
        if (! $stage) {
            return null;
        }

        $query = $db->select()->from(
            array('l' => 'director_deployment_log'),
            array('checksum' => $conn->dbHexFunc('l.config_checksum'))
        )->where('l.stage_name = ?', $stage);

        return $db->fetchOne($query);
    }

    protected function getDirectorObjects($type, $plural, $map)
    {
        $attrs = array_merge(
            array_keys($map),
            array('package', 'templates', 'active')
        );

        $objects = array();
        $result  = $this->getObjects($plural, $attrs, $this->getPackageName());
        foreach ($result as $name => $row) {
            $attrs = $row->attrs;

            $properties = array(
                'object_name' => $name,
                'object_type' => 'external_object'
            );

            foreach ($map as $key => $prop) {
                if (property_exists($attrs, $key)) {
                    $properties[$prop] = $attrs->$key;
                }
            }

            $objects[$name] = IcingaObject::createByType($type, $properties, $this->db);
        }

        return $objects;
    }

    /**
     * @return IcingaZone[]
     */
    public function getZoneObjects()
    {
        return $this->getDirectorObjects('Zone', 'zones', [
            'parent' => 'parent',
            'global' => 'is_global',
        ]);
    }

    public function getUserObjects()
    {
        return $this->getDirectorObjects('User', 'users', [
            'display_name' => 'display_name',
            'email'        => 'email',
            'groups'       => 'groups',
            'vars'         => 'vars',
        ]);
    }

    protected function buildEndpointZoneMap()
    {
        $zones = $this->getObjects('zones', ['endpoints'], $this->getPackageName());
        $zoneMap = array();

        foreach ($zones as $name => $zone) {
            if (! is_array($zone->attrs->endpoints)) {
                continue;
            }
            foreach ($zone->attrs->endpoints as $endpoint) {
                $zoneMap[$endpoint] = $name;
            }
        }

        return $zoneMap;
    }

    public function getEndpointObjects()
    {
        $zoneMap = $this->buildEndpointZoneMap();
        $objects = $this->getDirectorObjects('Endpoint', 'endpoints', [
            'host'         => 'host',
            'port'         => 'port',
            'log_duration' => 'log_duration',
        ]);

        foreach ($objects as $object) {
            $name = $object->object_name;
            if (array_key_exists($name, $zoneMap)) {
                $object->zone = $zoneMap[$name];
            }
        }

        return $objects;
    }

    public function getHostObjects()
    {
        $params = [
            'display_name'          => 'display_name',
            'address'               => 'address',
            'address6'              => 'address6',
            'templates'             => 'imports',
            'groups'                => 'groups',
            'vars'                  => 'vars',
            'check_command'         => 'check_command',
            'max_check_attempts'    => 'max_check_attempts',
            'check_period'          => 'check_period',
            'check_interval'        => 'check_interval',
            'retry_interval'        => 'retry_interval',
            'enable_notifications'  => 'enable_notifications',
            'enable_active_checks'  => 'enable_active_checks',
            'enable_passive_checks' => 'enable_passive_checks',
            'enable_event_handler'  => 'enable_event_handler',
            'enable_flapping'       => 'enable_flapping',
            'enable_perfdata'       => 'enable_perfdata',
            'event_command'         => 'event_command',
            'volatile'              => 'volatile',
            'zone'                  => 'zone',
            'command_endpoint'      => 'command_endpoint',
            'notes'                 => 'notes',
            'notes_url'             => 'notes_url',
            'action_url'            => 'action_url',
            'icon_image'            => 'icon_image',
            'icon_image_alt'        => 'icon_image_alt',
        ];

        if (version_compare($this->getVersion(), '2.8.0', '>=')) {
            $params['flapping_threshold_high'] = 'flapping_threshold_high';
            $params['flapping_threshold_low'] = 'flapping_threshold_low';
        }

        return $this->getDirectorObjects('Host', 'hosts', $params);
    }

    public function getHostGroupObjects()
    {
        return $this->getDirectorObjects('HostGroup', 'hostgroups', [
            'display_name' => 'display_name',
        ]);
    }

    public function getUserGroupObjects()
    {
        return $this->getDirectorObjects('UserGroup', 'usergroups', [
            'display_name' => 'display_name',
        ]);
    }

    /**
     * @return IcingaCommand[]
     */
    public function getCheckCommandObjects()
    {
        return $this->getSpecificCommandObjects('Check');
    }

    /**
     * @return IcingaCommand[]
     */
    public function getNotificationCommandObjects()
    {
        return $this->getSpecificCommandObjects('Notification');
    }

    /**
     * @return IcingaCommand[]
     */
    public function getEventCommandObjects()
    {
        return $this->getSpecificCommandObjects('Event');
    }

    /**
     * @return IcingaCommand[]
     */
    public function getSpecificCommandObjects($type)
    {
        IcingaCommand::setPluginDir($this->getConstant('PluginDir'));

        $objects = $this->getDirectorObjects('Command', "${type}Commands", [
            'arguments' => 'arguments',
            // 'env'      => 'env',
            'timeout'   => 'timeout',
            'command'   => 'command',
            'vars'      => 'vars',
        ]);
        foreach ($objects as $obj) {
            $obj->methods_execute = "Plugin$type";
        }

        return $objects;
    }

    public function listPackageStages($name, $active = null)
    {
        $packages = $this->getPackages();
        $found = array();

        if (array_key_exists($name, $packages)) {
            $package = $packages[$name];
            $current = $package->{'active-stage'};
            foreach ($package->stages as $stage) {
                if ($active === null) {
                    $found[] = $stage;
                } elseif ($active === true) {
                    if ($current === $stage) {
                        $found[] = $stage;
                    }
                } elseif ($active === false) {
                    if ($current !== $stage) {
                        $found[] = $stage;
                    }
                }
            }
        }

        return $found;
    }

    public function collectLogFiles(Db $db)
    {
        $existing = $this->listPackageStages($this->getPackageName());
        $missing = [];
        $empty = [];
        foreach (DirectorDeploymentLog::getUncollected($db) as $deployment) {
            $stage = $deployment->get('stage_name');
            if (! in_array($stage, $existing)) {
                $missing[] = $deployment;
                continue;
            }

            try {
                $availableFiles = $this->listStageFiles($stage);
            } catch (Exception $e) {
                // Could not collect stage files. Doesn't matter, let's try next time
                continue;
            }

            if (in_array('startup.log', $availableFiles)
                && in_array('status', $availableFiles)
            ) {
                if ($this->getStagedFile($stage, 'status') === '0') {
                    $deployment->set('startup_succeeded', 'y');
                } else {
                    $deployment->set('startup_succeeded', 'n');
                }
                $deployment->set('startup_log', $this->shortenStartupLog(
                    $this->getStagedFile($stage, 'startup.log')
                ));
            } else {
                // Stage seems to be incomplete, let's try again next time
                $empty[] = $deployment;
                continue;
            }
            $deployment->set('stage_collected', 'y');

            $deployment->store();
        }

        foreach ($missing as $deployment) {
            $deployment->set('stage_collected', 'n');
            $deployment->store();
        }

        $running = DirectorDeploymentLog::getRelatedToActiveStage($this, $db);
        if ($running !== null) {
            foreach ($empty as $deployment) {
                if ($deployment->getDeploymentTimestamp() < $running->getDeploymentTimestamp()) {
                    $deployment->set('stage_collected', 'n');
                    $deployment->store();
                    $this->deleteStage($this->getPackageName(), $deployment->get('stage_name'));
                }
            }
        }
    }

    public function wipeInactiveStages(Db $db)
    {
        $uncollected = DirectorDeploymentLog::getUncollected($db);
        $packageName = $this->getPackageName();
        foreach ($this->listPackageStages($packageName, false) as $stage) {
            if (array_key_exists($stage, $uncollected)) {
                continue;
            }
            $this->client()->delete('config/stages/' . $packageName . '/' . $stage);
        }
    }

    public function listStageFiles($stage)
    {
        return array_keys(
            $this->client()->get(sprintf(
                'config/stages/%s/%s',
                urlencode($this->getPackageName()),
                urlencode($stage)
            ))->getResult('name', array('type' => 'file'))
        );
    }

    public function getStagedFile($stage, $file)
    {
        return $this->client()->getRaw(sprintf(
            'config/files/%s/%s/%s',
            urlencode($this->getPackageName()),
            urlencode($stage),
            urlencode($file)
        ));
    }

    public function hasPackage($name)
    {
        $modules = $this->getPackages();
        return array_key_exists($name, $modules);
    }

    public function createPackage($name)
    {
        return $this->client()->post('config/packages/' . urlencode($name))->succeeded();
    }

    public function deletePackage($name)
    {
        return $this->client()->delete('config/packages/' . urlencode($name))->succeeded();
    }

    public function assertPackageExists($name)
    {
        if (! $this->hasPackage($name)) {
            if (! $this->createPackage($name)) {
                throw new RuntimeException(sprintf(
                    'Failed to create the package "%s" through the REST API',
                    $name
                ));
            }
        }

        return $this;
    }

    public function deleteStage($packageName, $stageName)
    {
        $this->client()->delete(sprintf(
            'config/stages/%s/%s',
            rawurlencode($packageName),
            rawurlencode($stageName)
        ))->succeeded();
    }

    /**
     * @throws Exception
     */
    public function stream()
    {
        $allTypes = array(
            'CheckResult',
            'StateChange',
            'Notification',
            'AcknowledgementSet',
            'AcknowledgementCleared',
            'CommentAdded',
            'CommentRemoved',
            'DowntimeAdded',
            'DowntimeRemoved',
            'DowntimeTriggered'
        );

        $queue = 'director-rand';

        $url = sprintf('events?queue=%s&types=%s', $queue, implode('&types=', $allTypes));

        $this->client()->request('post', $url, null, false, true);
    }

    /**
     * @param IcingaConfig $config
     * @param Db $db
     * @param null $packageName
     * @return \Icinga\Module\Director\Data\Db\DbObject
     * @throws \Icinga\Module\Director\Exception\DuplicateKeyException
     */
    public function dumpConfig(IcingaConfig $config, Db $db, $packageName = null)
    {
        if ($packageName === null) {
            $packageName = $db->settings()->get('icinga_package_name');
        }
        $start = microtime(true);
        $deployment = DirectorDeploymentLog::create(array(
            // 'config_id'      => $config->id,
            // 'peer_identity'  => $endpoint->object_name,
            'peer_identity'   => $this->client->getPeerIdentity(),
            'start_time'      => date('Y-m-d H:i:s'),
            'config_checksum' => $config->getChecksum(),
            'last_activity_checksum' => $config->getLastActivityChecksum()
            // 'triggered_by'   => Util::getUsername(),
            // 'username'       => Util::getUsername(),
            // 'module_name'    => $moduleName,
        ));

        /** @var DeploymentHook[] $hooks */
        $hooks = Hook::all('director/Deployment');
        foreach ($hooks as $hook) {
            $hook->beforeDeploy($deployment);
        }

        $this->assertPackageExists($packageName);

        $response = $this->client()->post('config/stages/' . urlencode($packageName), [
            'files' => $config->getFileContents()
        ]);

        $duration = (int) ((microtime(true) - $start) * 1000);
        // $deployment->duration_ms = $duration;
        $deployment->set('duration_dump', $duration);

        $succeeded = 'n';
        if ($response->succeeded()) {
            if ($stage = $response->getResult('stage', ['package' => $packageName])) { // Status?
                $deployment->set('stage_name', key($stage));
                $succeeded = 'y';
            }
        }
        $deployment->set('dump_succeeded', $succeeded);
        $deployment->store($db);

        if ($succeeded === 'y') {
            foreach ($hooks as $hook) {
                $hook->onSuccessfullDump($deployment);
            }
        }

        return $deployment;
    }

    protected function shortenStartupLog($log)
    {
        $logLen = strlen($log);
        if ($logLen < 1024 * 60) {
            return $log;
        }

        $part = substr($log, 0, 1024 * 20);
        $parts = explode("\n", $part);
        array_pop($parts);
        $begin = implode("\n", $parts) . "\n\n";

        $part = substr($log, -1024 * 20);
        $parts = explode("\n", $part);
        array_shift($parts);
        $end = "\n\n" . implode("\n", $parts);

        return $begin . sprintf(
            '[..] %d bytes removed by Director [..]',
            $logLen - (strlen($begin) + strlen($end))
        ) . $end;
    }

    protected function client()
    {
        if ($this->initialized === false) {
            $this->initialized = true;
            $this->enableWorkaroundForConnectionIssues();
        }

        return $this->client;
    }
}
