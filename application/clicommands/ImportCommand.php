<?php

namespace Icinga\Module\Director\Clicommands;

use Icinga\Module\Director\Cli\Command;
use Icinga\Module\Director\DirectorObject\Automation\ImportExport;

/**
 * Export Director Config Objects
 */
class ImportCommand extends Command
{
    /**
     * Export all ImportSource definitions
     *
     * Use this command to delete a single Icinga object
     *
     * USAGE
     *
     * icingacli director import importsource [options]
     *
     * OPTIONS
     *
     *   --no-pretty   JSON is pretty-printed per default
     *                 Use this flag to enforce unformatted JSON
     */
    public function importsourceAction()
    {
        $json = file_get_contents('php://stdin');
        $export = new ImportExport($this->db());
        $export->unserializeImportSources(json_decode($json));
    }

    /**
     * Import SyncRule definitions
     *
     * Use this command to import ....
     *
     * USAGE
     *
     * icingacli director syncrule export [options]
     *
     * OPTIONS
     *
     *   --no-pretty   JSON is pretty-printed per default
     *                 Use this flag to enforce unformatted JSON
     */
    public function syncruleAction()
    {
        $json = file_get_contents('php://stdin');
        $export = new ImportExport($this->db());
        $export->unserializeSyncRules(json_decode($json));
    }
}
