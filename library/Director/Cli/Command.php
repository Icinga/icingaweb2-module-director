<?php

namespace Icinga\Module\Director\Cli;

use Icinga\Cli\Command as CliCommand;
use Icinga\Module\Director\Db;

class Command extends CliCommand
{
    protected $db;

    protected function db()
    {
        if ($this->db === null) {
            $resourceName = $this->Config()->get('db', 'resource');
            if ($resourceName) {
                $this->db = Db::fromResourceName($resourceName);
            } else {
                $this->fail('Director is not configured correctly');
            }
        }

        return $this->db;
    }
}
