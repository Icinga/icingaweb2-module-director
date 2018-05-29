<?php

namespace Icinga\Module\Director\Clicommands;

use Icinga\Module\Director\Cli\Command;
use Icinga\Module\Director\DirectorObject\Automation\ImportExport;

/**
 * Export Director Config Objects
 */
class ExportCommand extends Command
{
    /**
     * Export all ImportSource definitions
     *
     * Use this command to delete a single Icinga object
     *
     * USAGE
     *
     * icingacli director export importsource [options]
     *
     * OPTIONS
     *
     *   --no-pretty   JSON is pretty-printed per default
     *                 Use this flag to enforce unformatted JSON
     */
    public function importsourceAction()
    {
        $export = new ImportExport($this->db());
        echo $this->renderJson(
            $export->serializeAllImportSources(),
            !$this->params->shift('no-pretty')
        );
    }

    /**
     * Export all SyncRule definitions
     *
     * Use this command to delete a single Icinga object
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
        $export = new ImportExport($this->db());
        echo $this->renderJson(
            $export->serializeAllSyncRules(),
            !$this->params->shift('no-pretty')
        );
    }

    /**
     * Export all Job definitions
     *
     * USAGE
     *
     * icingacli director export job [options]
     *
     * OPTIONS
     *
     *   --no-pretty   JSON is pretty-printed per default
     *                 Use this flag to enforce unformatted JSON
     */
    public function jobAction()
    {
        $export = new ImportExport($this->db());
        echo $this->renderJson(
            $export->serializeAllJobs(),
            !$this->params->shift('no-pretty')
        );
    }

    /**
     * Export all DataField definitions
     *
     * USAGE
     *
     * icingacli director export datafield [options]
     *
     * OPTIONS
     *
     *   --no-pretty   JSON is pretty-printed per default
     *                 Use this flag to enforce unformatted JSON
     */
    public function datafieldAction()
    {
        $export = new ImportExport($this->db());
        echo $this->renderJson(
            $export->serializeAllDataFields(),
            !$this->params->shift('no-pretty')
        );
    }

    /**
     * Export all DataList definitions
     *
     * USAGE
     *
     * icingacli director export datafield [options]
     *
     * OPTIONS
     *
     *   --no-pretty   JSON is pretty-printed per default
     *                 Use this flag to enforce unformatted JSON
     */
    public function datalistAction()
    {
        $export = new ImportExport($this->db());
        echo $this->renderJson(
            $export->serializeAllDataLists(),
            !$this->params->shift('no-pretty')
        );
    }

    /**
     * Export all IcingaHostGroup definitions
     *
     * USAGE
     *
     * icingacli director export hostgroup [options]
     *
     * OPTIONS
     *
     *   --no-pretty   JSON is pretty-printed per default
     *                 Use this flag to enforce unformatted JSON
     */
    public function hostgroupAction()
    {
        $export = new ImportExport($this->db());
        echo $this->renderJson(
            $export->serializeAllHostGroups(),
            !$this->params->shift('no-pretty')
        );
    }

    /**
     * Export all IcingaServiceGroup definitions
     *
     * USAGE
     *
     * icingacli director export servicegroup [options]
     *
     * OPTIONS
     *
     *   --no-pretty   JSON is pretty-printed per default
     *                 Use this flag to enforce unformatted JSON
     */
    public function servicegroupAction()
    {
        $export = new ImportExport($this->db());
        echo $this->renderJson(
            $export->serializeAllServiceGroups(),
            !$this->params->shift('no-pretty')
        );
    }

    /**
     * Export all IcingaTemplateChoiceHost definitions
     *
     * USAGE
     *
     * icingacli director export hosttemplatechoice [options]
     *
     * OPTIONS
     *
     *   --no-pretty   JSON is pretty-printed per default
     *                 Use this flag to enforce unformatted JSON
     */
    public function hosttemplatechoiceAction()
    {
        $export = new ImportExport($this->db());
        echo $this->renderJson(
            $export->serializeAllHostTemplateChoices(),
            !$this->params->shift('no-pretty')
        );
    }
}
