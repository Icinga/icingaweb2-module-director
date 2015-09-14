<?php

namespace Icinga\Module\Director\Clicommands;

use Icinga\Cli\Command;
use Icinga\Module\Director\Db;
use Icinga\Module\Director\Objects\ImportSource;
use Icinga\Module\Director\Import\Import;

class ImportCommand extends Command
{
    protected $db;

    public function runAction()
    {
        if ($runId = Import::run($id = ImportSource::load($this->params->shift(), $this->db()))) {
            echo "Triggered new import\n";
        } else {
            echo "Nothing changed\n";
        }
    }


    protected function db()
    {
        if ($this->db === null) {
            $this->app->setupZendAutoloader();
            $resourceName = $this->Config()->get('db', 'resource');
            if ($resourceName) {
                $this->db = Db::fromResourceName($resourceName);
            } else {
                $this->fail('Director is not confiured correctly');
            }
        }

        return $this->db;
    }

}

