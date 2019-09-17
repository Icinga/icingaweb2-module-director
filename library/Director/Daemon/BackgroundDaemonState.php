<?php

namespace Icinga\Module\Director\Daemon;

use Icinga\Module\Director\Db;

class BackgroundDaemonState
{
    protected $db;

    /** @var RunningDaemonInfo[] */
    protected $instances;

    public function __construct(Db $db)
    {
        $this->db = $db;
    }

    public function hasProblems()
    {
        return $this->isRunning();
    }

    public function isRunning()
    {
        foreach ($this->getInstances() as $instance) {
            if ($instance->isRunning()) {
                return true;
            }
        }

        return false;
    }

    public function getInstances()
    {
        if ($this->instances === null) {
            $this->instances = $this->fetchInfo();
        }

        return $this->instances;
    }

    /**
     * @return RunningDaemonInfo[]
     */
    protected function fetchInfo()
    {
        $db = $this->db->getDbAdapter();
        $daemons = $db->fetchAll(
            $db->select()->from('director_daemon_info')->order('fqdn')->order('username')->order('pid')
        );

        $result = [];
        foreach ($daemons as $info) {
            $result[] = new RunningDaemonInfo($info);
        }

        return $result;
    }
}
