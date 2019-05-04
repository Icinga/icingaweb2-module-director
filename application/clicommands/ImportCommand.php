<?php

namespace Icinga\Module\Director\Clicommands;

use Icinga\Module\Director\Cli\Command;
use Icinga\Module\Director\DirectorObject\Automation\ImportExport;
use Icinga\Module\Director\Objects\ImportSource;

/**
 * Export Director Config Objects
 */
class ImportCommand extends Command
{
    /**
     * Import ImportSource definitions
     *
     * USAGE
     *
     * icingacli director import importsources < importsources.json
     *
     * OPTIONS
     */
    public function importsourcesAction()
    {
        $json = file_get_contents('php://stdin');
        $import = new ImportExport($this->db());
        $count = $import->unserializeImportSources(json_decode($json));
        echo "$count Import Sources have been imported\n";
    }

    // /**
    //  * Import an ImportSource definition
    //  *
    //  * USAGE
    //  *
    //  * icingacli director import importsource < importsource.json
    //  *
    //  * OPTIONS
    //  */
    // public function importsourcection()
    // {
    //     $json = file_get_contents('php://stdin');
    //     $object = ImportSource::import(json_decode($json), $this->db());
    //     $object->store();
    //     printf("Import Source '%s' has been imported\n", $object->getObjectName());
    // }

    /**
     * Import SyncRule definitions
     *
     * USAGE
     *
     * icingacli director import syncrules < syncrules.json
     */
    public function syncrulesAction()
    {
        $json = file_get_contents('php://stdin');
        $import = new ImportExport($this->db());
        $count = $import->unserializeSyncRules(json_decode($json));
        echo "$count Sync Rules have been imported\n";
    }
}
