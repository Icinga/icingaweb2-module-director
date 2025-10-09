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
     * USAGE
     *
     * icingacli director export importsources [options]
     *
     * OPTIONS
     *
     *   --no-pretty   JSON is pretty-printed per default
     *                 Use this flag to enforce unformatted JSON
     */
    public function importsourcesAction()
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
     * USAGE
     *
     * icingacli director export syncrules [options]
     *
     * OPTIONS
     *
     *   --no-pretty   JSON is pretty-printed per default
     *                 Use this flag to enforce unformatted JSON
     */
    public function syncrulesAction()
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
     * icingacli director export jobs [options]
     *
     * OPTIONS
     *
     *   --no-pretty   JSON is pretty-printed per default
     *                 Use this flag to enforce unformatted JSON
     */
    public function jobsAction()
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
     * icingacli director export datafields [options]
     *
     * OPTIONS
     *
     *   --no-pretty   JSON is pretty-printed per default
     *                 Use this flag to enforce unformatted JSON
     */
    public function datafieldsAction()
    {
        $export = new ImportExport($this->db());
        echo $this->renderJson(
            $export->serializeAllDataFields(),
            !$this->params->shift('no-pretty')
        );
    }

    /**
     * Export all CustomProperty definitions
     *
     * USAGE
     *
     * icingacli director export customproperties [options]
     *
     * OPTIONS
     *
     *   --no-pretty   JSON is pretty-printed per default
     *                 Use this flag to enforce unformatted JSON
     */
    public function custompropertiesAction()
    {
        $export = new ImportExport($this->db());
        echo $this->renderJson(
            $export->serializeAllCustomProperties(),
            ! $this->params->shift('no-pretty')
        );
    }

    /**
     * Export all DataList definitions
     *
     * USAGE
     *
     * icingacli director export datalists [options]
     *
     * OPTIONS
     *
     *   --no-pretty   JSON is pretty-printed per default
     *                 Use this flag to enforce unformatted JSON
     */
    public function datalistsAction()
    {
        $export = new ImportExport($this->db());
        echo $this->renderJson(
            $export->serializeAllDataLists(),
            !$this->params->shift('no-pretty')
        );
    }

    // /**
    //  * Export all IcingaHostGroup definitions
    //  *
    //  * USAGE
    //  *
    //  * icingacli director export hostgroup [options]
    //  *
    //  * OPTIONS
    //  *
    //  *   --no-pretty   JSON is pretty-printed per default
    //  *                 Use this flag to enforce unformatted JSON
    //  */
    // public function hostgroupAction()
    // {
    //     $export = new ImportExport($this->db());
    //     echo $this->renderJson(
    //         $export->serializeAllHostGroups(),
    //         !$this->params->shift('no-pretty')
    //     );
    // }
    //
    // /**
    //  * Export all IcingaServiceGroup definitions
    //  *
    //  * USAGE
    //  *
    //  * icingacli director export servicegroup [options]
    //  *
    //  * OPTIONS
    //  *
    //  *   --no-pretty   JSON is pretty-printed per default
    //  *                 Use this flag to enforce unformatted JSON
    //  */
    // public function servicegroupAction()
    // {
    //     $export = new ImportExport($this->db());
    //     echo $this->renderJson(
    //         $export->serializeAllServiceGroups(),
    //         !$this->params->shift('no-pretty')
    //     );
    // }

    /**
     * Export all IcingaTemplateChoiceHost definitions
     *
     * USAGE
     *
     * icingacli director export hosttemplatechoices [options]
     *
     * OPTIONS
     *
     *   --no-pretty   JSON is pretty-printed per default
     *                 Use this flag to enforce unformatted JSON
     */
    public function hosttemplatechoicesAction()
    {
        $export = new ImportExport($this->db());
        echo $this->renderJson(
            $export->serializeAllHostTemplateChoices(),
            !$this->params->shift('no-pretty')
        );
    }
}
