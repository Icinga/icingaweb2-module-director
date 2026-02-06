<?php

namespace Icinga\Module\Director\Clicommands;

use Icinga\Application\Benchmark;
use Icinga\Data\Filter\Filter;
use Icinga\Data\Filter\FilterAnd;
use Icinga\Data\Filter\FilterGreaterThan;
use Icinga\Data\Filter\FilterMatch;
use Icinga\Module\Director\Cli\Command;

/**
 * Handle DB migrations
 *
 * This command retrieves information about unapplied database migration and
 * helps applying them.
 */
class MigrateCommand extends Command
{
    /**
     * Run any pending migrations
     *
     * icingacli director migrate datafields --dry-run
     */
    public function datafieldsAction()
    {
        $db = $this->db();
        $datafieldQuery = $db->select()
            ->from(['dd' => 'director_datafield'], ['varname' => 'dd.varname', 'datatype' => 'dd.datatype', 'count' => 'COUNT(varname)'])
            ->group('varname');
        $duplicateFieldsQuery = clone $datafieldQuery;
        $sqlDirectorObjectFieldsQuery = clone $datafieldQuery;
        $datafieldQuery = $datafieldQuery->addFilter(Filter::not(
            FilterAnd::matchAny(
                FilterMatch::where('datatype', '*SqlQuery'),
                FilterMatch::where('datatype', '*DirectorObject'),
                FilterMatch::where('datatype', '*Dictionary')
            )
        ));

        $datafieldQuery->select()
            ->having('count = 1');

        $duplicateFieldsQuery->select()->having('count > 1');

        $sqlDirectorObjectFieldsQuery->addFilter(Filter::not(
            $datafieldQuery->getFilter()
        ));

        if ($this->params->get('dry-run')) {
            printf("The following %d datafields will be migrated to new custom properties:\n", $datafieldQuery->count());
            foreach ($datafieldQuery as $row) {
                printf(
                    "Var name: %s | Data type: %s | count: %d\n",
                    $row->varname,
                    substr($row->datatype, strlen("Icinga\Module\Director\DataType\DataType")),
                    $row->count
                );
            }

            printf("The following datafields will not be migrated as they are of data type 'DirectorObject' or 'SQLQuery':\n");
            foreach ($sqlDirectorObjectFieldsQuery as $row) {
                printf("Var name: %s | count: %d\n", $row->varname, $row->count);
            }
            
            printf("The following datafields will not be migrated as there are duplicates:\n");
            foreach ($duplicateFieldsQuery as $row) {
                printf("Var name: %s | count: %d\n", $row->varname, $row->count);
            }
        }

        echo "Not yet implemented\n";
    }
}
