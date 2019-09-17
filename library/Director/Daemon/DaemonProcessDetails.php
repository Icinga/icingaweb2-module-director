<?php

namespace Icinga\Module\Director\Daemon;

use gipfl\LinuxHealth\Memory;
use Icinga\Application\Platform;
use React\ChildProcess\Process;
use gipfl\Cli\Process as CliProcess;

class DaemonProcessDetails
{
    /** @var string */
    protected $instanceUuid;

    /** @var \stdClass */
    protected $info;

    /** @var ProcessList[] */
    protected $processLists = [];

    protected $myArgs;

    protected $myPid;

    public function __construct($instanceUuid)
    {
        $this->instanceUuid = $instanceUuid;
        $this->initialize();
    }

    public function getInstanceUuid()
    {
        return $this->instanceUuid;
    }

    public function getPropertiesToInsert()
    {
        return $this->getPropertiesToUpdate() + (array) $this->info;
    }

    public function getPropertiesToUpdate()
    {
        return [
            'ts_last_update' => DaemonUtil::timestampWithMilliseconds(),
            'ts_stopped'     => null,
            'process_info'   => \json_encode($this->collectProcessInfo()),
        ];
    }

    public function set($property, $value)
    {
        if (\property_exists($this->info, $property)) {
            $this->info->$property = $value;
        } else {
            throw new \InvalidArgumentException("Trying to set invalid daemon info property: $property");
        }
    }

    public function registerProcessList(ProcessList $list)
    {
        $refresh = function (Process $process) {
            $this->refreshProcessInfo();
        };
        $list->on('start', $refresh)->on('exit', $refresh);
        $this->processLists[] = $list;

        return $this;
    }

    protected function refreshProcessInfo()
    {
        $this->set('process_info', \json_encode($this->collectProcessInfo()));
    }

    protected function collectProcessInfo()
    {
        $info = (object) [$this->myPid => (object) [
            'command' => implode(' ', $this->myArgs),
            'running' => true,
            'memory'  => Memory::getUsageForPid($this->myPid)
        ]];

        foreach ($this->processLists as $processList) {
            foreach ($processList->getOverview() as $pid => $details) {
                $info->$pid = $details;
            }
        }

        return $info;
    }

    protected function initialize()
    {
        global $argv;
        CliProcess::getInitialCwd();
        $this->myArgs = $argv;
        $this->myPid = \posix_getpid();
        if (isset($_SERVER['_'])) {
            $self = $_SERVER['_'];
        } else {
            // Process does a better job, but want the relative path (if such)
            $self = $_SERVER['PHP_SELF'];
        }
        $this->info = (object) [
            'instance_uuid_hex'    => $this->instanceUuid,
            'running_with_systemd' => 'n',
            'ts_started'           => (int) ((float) $_SERVER['REQUEST_TIME_FLOAT'] * 1000),
            'ts_stopped'           => null,
            'pid'                  => \posix_getpid(),
            'fqdn'                 => Platform::getFqdn(),
            'username'             => Platform::getPhpUser(),
            'schema_version'       => null,
            'php_version'          => Platform::getPhpVersion(),
            'binary_path'          => $self,
            'binary_realpath'      => CliProcess::getBinaryPath(),
            'php_integer_size'     => PHP_INT_SIZE,
            'php_binary_path'      => PHP_BINARY,
            'php_binary_realpath'  => \realpath(PHP_BINARY), // TODO: useless?
            'process_info'         => null,
        ];
    }
}
