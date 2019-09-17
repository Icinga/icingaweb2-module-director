<?php

namespace Icinga\Module\Director\Daemon;

class RunningDaemonInfo
{
    /** @var object */
    protected $info;

    public function __construct($info = null)
    {
        $this->setInfo($info);
    }

    public function setInfo($info)
    {
        if (empty($info)) {
            $this->info = $this->createEmptyInfo();
        } else {
            $this->info = $info;
        }

        return $this;
    }

    public function isRunning()
    {
        return $this->getPid() !== null && ! $this->isOutdated();
    }

    public function getPid()
    {
        return (int) $this->info->pid;
    }

    public function getUsername()
    {
        return $this->info->username;
    }

    public function getFqdn()
    {
        return $this->info->fqdn;
    }

    public function getLastUpdate()
    {
        return $this->info->ts_last_update;
    }

    public function getLastModification()
    {
        return $this->info->ts_last_modification;
    }

    public function getPhpVersion()
    {
        return $this->info->php_version;
    }

    public function hasBeenStopped()
    {
        return $this->getTimestampStopped() !== null;
    }

    public function getTimestampStarted()
    {
        return $this->info->ts_started;
    }

    public function getTimestampStopped()
    {
        return $this->info->ts_stopped;
    }

    public function isOutdated($seconds = 5)
    {
        return (
            DaemonUtil::timestampWithMilliseconds() - $this->info->ts_last_update
        ) > $seconds * 1000;
    }

    public function isRunningWithSystemd()
    {
        return $this->info->running_with_systemd === 'y';
    }

    public function getBinaryPath()
    {
        return $this->info->binary_path;
    }

    public function getBinaryRealpath()
    {
        return $this->info->binary_realpath;
    }

    public function binaryRealpathDiffers()
    {
        return $this->getBinaryPath() !== $this->getBinaryRealpath();
    }

    public function getPhpBinaryPath()
    {
        return $this->info->php_binary_path;
    }

    public function getPhpBinaryRealpath()
    {
        return $this->info->php_binary_realpath;
    }

    public function phpBinaryRealpathDiffers()
    {
        return $this->getPhpBinaryPath() !== $this->getPhpBinaryRealpath();
    }

    public function getPhpIntegerSize()
    {
        return (int) $this->info->php_integer_size;
    }

    public function has64bitIntegers()
    {
        return $this->getPhpIntegerSize() === 8;
    }

    /*
    // TODO: not yet
    public function isMaster()
    {
        return $this->info->is_master === 'y';
    }

    public function isStandby()
    {
        return ! $this->isMaster();
    }
    */

    protected function createEmptyInfo()
    {
        return (object) [
            'pid'                    => null,
            'fqdn'                   => null,
            'username'               => null,
            'php_version'            => null,
            // 'is_master'              => null,
            // Only if not running. Does this make any sense in 'empty info'?
            'ts_last_update'         => null,
            'ts_last_modification'   => null
        ];
    }
}
