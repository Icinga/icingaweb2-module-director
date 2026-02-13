<?php

namespace Icinga\Module\Director\Clicommands;

use Icinga\Data\Db\DbQuery;
use Icinga\Data\Filter\Filter;
use Icinga\Data\Filter\FilterAnd;
use Icinga\Data\Filter\FilterMatch;
use Icinga\Module\Director\Cli\Command;
use Icinga\Module\Director\Db\DbSelectParenthesis;
use Icinga\Module\Director\Objects\DirectorDatafield;
use Icinga\Module\Director\Objects\DirectorProperty;
use Ramsey\Uuid\Uuid;

/**
 * Handle DB migrations
 *
 * This command retrieves information about unapplied database migration and
 * helps applying them.
 */
class MigrateCommand extends Command
{
    private $existingCustomProperties = [];

    /**
     * Run any pending migrations
     *
     * icingacli director migrate datafields --dry-run --verbose
     */
    public function datafieldsAction()
    {
        $db = $this->db();
        $customPropertiesToMigrate = $this->prepareCustomProperties();
        // Dry run summary
        if ($this->params->get('dry-run')) {
            $this->checkMigrateableDatafieldTypes();
            $this->checkProtectedDatafields();
            $this->checkDatafieldsWithCategory();
            $this->checkUnmigrateableDatafieldTypes();
            $this->checkDatafieldsWithDuplicateNames();
            printf(
                "Number of datafields that can not be migrated as the custom properties with the same name already"
                . " exists: %d\n",
                count($this->existingCustomProperties)
            );

            return;
        }

        echo "Migrating Data fields\n";
        foreach ($this->existingCustomProperties as $varname) {
            unset($customPropertiesToMigrate[$varname]);

            if ($this->isVerbose) {
                echo "[-] Skipping migrating datafield '$varname' as a custom property with the same name already exists\n";
            }
        }

        $typeOffset = strlen("Icinga\Module\Director\DataType\DataType");
        if ($this->isVerbose) {
            foreach ($this->getDatafieldsWithUnsupportedValuetype() as $varname => $datatype) {
                $dataType = substr($datatype, $typeOffset);

                echo "[-] Skipping migrating datafield '$varname' as it has an unsupported datatype '$dataType'\n";
            }

            foreach ($this->getDatafieldsWithCategory() as $varname) {
                echo "[-] Skipping migrating datafield '$varname' as it belongs to a category\n";
            }

            foreach ($this->getDatafieldsWithProtectedValues() as $varname) {
                echo "[-] Skipping migrating datafield '$varname' as it is protected\n";
            }

            foreach ($this->getDatafieldsWithDuplicateNames() as $varname => $count) {
                printf("[-] Skipping migrating datafield '%s' as there are '%d' datafields with same name\n", $varname, $count) ;
            }
        }

        if (! empty($customPropertiesToMigrate)) {
            $db->getDbAdapter()->beginTransaction();
            $this->migrateDatafields($customPropertiesToMigrate);
            $db->getDbAdapter()->commit();
        }

        echo "Migration completed\n";

        $totalMigrated = count($customPropertiesToMigrate);
        $totalSkipped = count(DirectorDatafield::loadAll($db)) - $totalMigrated;

        echo "Summary:\n";
        printf("Total datafields migrated: %d\n", $totalMigrated);
        printf("Total datafields skipped: %d\n", $totalSkipped);
    }

    /**
     * Prepare custom properties to migrate
     *
     * @return array
     */
    private function prepareCustomProperties(): array
    {
        $db = $this->db();
        $directorProperty = DirectorProperty::loadAll(
            $db,
            $db->getDbAdapter()->select()->from('director_property')->where('parent_uuid IS NULL'),
            'key_name'
        );

        $customProperties = [];
        $migrationQuery = $this->getDataFieldsMigrationQuery();
        $typeOffset = strlen("Icinga\Module\Director\DataType\DataType");
        foreach ($migrationQuery as $row) {
            if (isset($directorProperty[$row->varname])) {
                $this->existingCustomProperties[] = $row->varname;

                continue;
            }

            $customProperty = [
                'uuid' => Uuid::uuid4()->getBytes(),
                'key_name' => $row->varname,
                'label' => $row->caption,
                'description' => $row->description
            ];
            $dataType = strtolower(substr($row->datatype, $typeOffset));

            if ($dataType === 'array') {
                $customProperty['value_type'] = 'dynamic-array';
                $customProperty['item_type'] = 'string';
            } elseif ($dataType === 'boolean' || $dataType === 'number' || $dataType === 'string') {
                $customProperty['value_type'] = $dataType;
            } elseif ($dataType === 'datalist') {
                $datalist = DirectorDatafield::load($row->id, $db);
                $settings = $datalist->getSettings();
                $behaviour = $settings['behavior'];
                if ($behaviour === 'strict' || $behaviour === 'suggest_strict') {
                    $customProperty['value_type'] = 'datalist-strict';
                } else {
                    $customProperty['value_type'] = 'datalist-non-strict';
                }

                $customProperty['item_type'] = $settings['data_type'] === 'array'
                    ? 'dynamic-array'
                    : 'string';
            } else {
                $customProperty['value_type'] = "unsupported-$dataType";
            }

            $customProperties[$row->varname] = $customProperty;
        }

        return $customProperties;
    }

    /**
     * Migrate given prepared custom properties
     *
     * @param array $customProperties
     *
     * @return void
     */
    private function migrateDatafields(array $customProperties): void
    {
        $db = $this->db();
        foreach ($customProperties as $varName => $customProperty) {
            if (str_starts_with($customProperty['value_type'], 'unsupported-')) {
                echo "[-] Skipping migration of datafield '{$varName}' as it has an unsupported datatype '"
                    . substr($customProperty['value_type'], strlen('unsupported-'))
                    . "'\n";

                continue;
            }

            $itemType = null;
            if (isset($customProperty['item_type'])) {
                $itemType = $customProperty['item_type'];
                unset($customProperty['item_type']);
            }

            $db->insert('director_property', $customProperty);

            if ($itemType !== null) {
                $db->insert('director_property', [
                    'uuid' => Uuid::uuid4()->getBytes(),
                    'key_name' => 0,
                    'value_type' => $itemType,
                    'parent_uuid' => $customProperty['uuid']
                ]);
            }

            if ($this->isVerbose) {
                echo "[+] Datafield '$varName' successfully migrated\n";
            }
        }
    }

    /**
     * Check what datafield types can be migrated
     *
     * @return void
     */
    private function checkMigrateableDatafieldTypes(): void
    {
        $db = $this->db();
        printf("The following datafield types and the corresponding number of datafields can be migrated:\n");
        $total = 0;
        $query = $this->getDataFieldsMigrationQuery();
        $typeOffset = strlen("Icinga\Module\Director\DataType\DataType");
        foreach (
            $db->select()->from(
                ['q' =>  new DbSelectParenthesis($query->getSelectQuery())],
                ['*', 'count_q' => 'COUNT(*)']
            )->group('datatype') as $row
        ) {
            printf(
                "Data type: %s | count: %d\n",
                substr($row->datatype, $typeOffset),
                $row->count_q
            );
            $total += $row->count_q;
        }

        printf("Total datafields that can be migrated: %d\n\n", $total);
    }

    /**
     * Check what datafield types can not be migrated
     *
     * @return void
     */
    private function checkUnmigrateableDatafieldTypes(): void
    {
        printf("The following datafield types and the corresponding number of datafields can not be migrated:\n");
        $total = 0;
        $groupByDataType = [];
        foreach ($this->getDatafieldsWithUnsupportedValuetype() as $varname => $datatype) {
            $groupByDataType[$datatype][] = $varname;
            $total++;
        }

        foreach ($groupByDataType as $datatype => $datafields) {
            printf("Data type: %s | count: %d\n", $datatype, count($datafields));
        }

        if ($total > 0) {
            printf("Total datafields that can not be migrated because of incompatible datatypes with new custom property support: %d\n\n", $total);
        }
    }

    /**
     * Get query for datafields that can be migrated
     *
     * @return DbQuery
     */
    private function getDataFieldsMigrationQuery(): DbQuery
    {
        $query = $this->getDataFieldQuery();
        $skippedFields = array_merge(
            array_keys($this->getDatafieldsWithDuplicateNames()),
            array_keys($this->getDatafieldsWithUnsupportedValuetype()),
            $this->getDatafieldsWithProtectedValues(),
            $this->getDatafieldsWithCategory()
        );

        $query->addFilter(Filter::not(Filter::where('varname', $skippedFields)));

        return $query;
    }

    /**
     * Check what datafields can not be migrated because they belong to a category
     *
     * @return void
     */
    private function checkDatafieldsWithCategory(): void
    {
        $count = count($this->getDatafieldsWithCategory());

        if ($count > 0) {
            printf("The following number of datafields belong to a category and can not be migrated: %d\n\n", $count);
        }
    }

    /**
     * Check what datafields can not be migrated because they have duplicate names
     *
     * @return void
     */
    private function checkDatafieldsWithDuplicateNames(): void
    {
        printf("The following datafields can not be migrated as there are duplicates:\n");
        $total = 0;
        foreach ($this->getDatafieldsWithDuplicateNames() as $varname => $count) {
            printf("Var name: %s | count: %d\n", $varname, $count);
            $total += $count;
        }

        printf("Total datafields that can not be migrated because of having duplicates: %d\n\n", $total);
    }

    /**
     * Check what datafields can not be migrated because they are protected
     *
     * @return void
     */
    private function checkProtectedDatafields(): void
    {
        $count = count($this->getDatafieldsWithProtectedValues());

        if ($count > 0) {
            printf("The following number of datafields are protected and can not be migrated: %d\n\n", $count);
        }
    }

    /**
     * Get query for datafields
     *
     * @return DbQuery
     */
    private function getDataFieldQuery(): DbQuery
    {
        return $this->db()->select()
            ->from(
                ['dd' => 'director_datafield'],
                [
                    'id' => 'dd.id',
                    'varname' => 'dd.varname',
                    'caption' => 'dd.caption',
                    'description' => 'dd.description',
                    'datatype' => 'dd.datatype',
                    'count' => 'COUNT(varname)'
                ]
            )->group('varname');
    }

    /**
     * Get datafields with unsupported value type in new custom property support
     *
     * @return array
     */
    private function getDatafieldsWithUnsupportedValuetype()
    {
        $query = $this->getDataFieldQuery();
        $query->addFilter(FilterAnd::matchAny(
            FilterMatch::where('datatype', '*SqlQuery'),
            FilterMatch::where('datatype', '*DirectorObject'),
            FilterMatch::where('datatype', '*Dictionary')
        ));

        $query->columns(['varname', 'datatype']);

        return $query->fetchPairs();
    }

    /**
     * Get datafields with duplicate names
     *
     * @return array
     */
    private function getDatafieldsWithDuplicateNames(): array
    {
        $query = $this->getDataFieldQuery();
        $query->columns(['varname', 'count' => 'COUNT(varname)']);
        $query->select()->having('count > 1');

        return $query->fetchPairs();
    }

    /**
     * Get datafields with protected values
     *
     * @return array
     */
    private function getDatafieldsWithProtectedValues(): array
    {
        $query = $this->getDataFieldQuery();
        $query->joinLeft(['dds' => 'director_datafield_setting'], "dd.id = dds.datafield_id AND dds.setting_name = 'visibility'", []);
        $query->addFilter(Filter::matchAll(
            FilterMatch::where('dd.datatype', '*String'),
            FilterMatch::where('dds.setting_value', 'hidden')
        ))->addFilter(Filter::fromQueryString('category_id IS NULL'));

        $query->columns(['varname']);

        return $query->fetchColumn();
    }

    /**
     * Get datafields with categories
     *
     * @return array
     */
    private function getDatafieldsWithCategory(): array
    {
        $query = $this->getDataFieldQuery();
        $query->addFilter(Filter::fromQueryString('category_id IS NOT NULL'));
        $query->columns(['varname']);

        return $query->fetchColumn();
    }
}
