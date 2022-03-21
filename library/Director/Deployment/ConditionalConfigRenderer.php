<?php

namespace Icinga\Module\Director\Deployment;

use Icinga\Exception\NotFoundError;
use Icinga\Module\Director\Db;
use Icinga\Module\Director\IcingaConfig\IcingaConfig;
use Icinga\Module\Director\Objects\DirectorActivityLog;

class ConditionalConfigRenderer
{
    /** @var Db */
    protected $db;

    protected $forceRendering = false;

    public function __construct(Db $connection)
    {
        $this->db = $connection;
    }

    public function forceRendering($force = true)
    {
        $this->forceRendering = $force;

        return $this;
    }

    public function getConfig()
    {
        if ($this->shouldGenerate()) {
            return IcingaConfig::generate($this->db);
        }

        return $this->loadLatestActivityConfig();
    }

    protected function loadLatestActivityConfig()
    {
        $db = $this->db;

        return IcingaConfig::loadByActivityChecksum($db->getLastActivityChecksum(), $db);
    }

    protected function shouldGenerate()
    {
        return $this->forceRendering || !$this->configForLatestActivityExists();
    }

    protected function configForLatestActivityExists()
    {
        $db = $this->db;
        try {
            $latestActivity = DirectorActivityLog::loadLatest($db);
        } catch (NotFoundError $e) {
            return false;
        }

        return IcingaConfig::existsForActivityChecksum(
            bin2hex($latestActivity->get('checksum')),
            $db
        );
    }
}
