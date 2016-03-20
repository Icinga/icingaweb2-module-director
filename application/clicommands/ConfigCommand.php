<?php

namespace Icinga\Module\Director\Clicommands;

use Icinga\Application\Benchmark;
use Icinga\Module\Director\Cli\Command;
use Icinga\Module\Director\IcingaConfig\IcingaConfig;
use Icinga\Module\Director\Data\Db\DbObject;

class ConfigCommand extends Command
{
    public function renderAction()
    {
        $config = new IcingaConfig($this->db());
        Benchmark::measure('Rendering config');
        if ($config->hasBeenModified()) {
            Benchmark::measure('Config rendered, storing to db');
            $config->store();
            Benchmark::measure('All done');
            $checksum = $config->getHexChecksum();
            printf(
                "New config with checksum %s has been generated\n",
                $checksum
            );
        } else {
            $checksum = $config->getHexChecksum();
            printf(
                "Config with checksum %s already exists\n",
                $checksum
            );
        }
    }

    public function filesAction()
    {
    }

    public function fileAction()
    {
    }
}
