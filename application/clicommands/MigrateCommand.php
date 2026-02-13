<?php

namespace Icinga\Module\Director\Clicommands;

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
    /**
     * Run any pending migrations
     *
     * icingacli director migrate datafields --dry-run --verbose
     */
    public function datafieldsAction()
    {
        $db = $this->db();
        $datafieldQuery = $db->select()
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
            );
        $categoryFieldsQuery = clone $datafieldQuery;
        $datafieldQuery->group('dd.varname');
        $duplicateFieldsQuery = clone $datafieldQuery;
        $unsupportedTypeQuery = clone $datafieldQuery;
        $filter = FilterAnd::matchAny(
            FilterMatch::where('datatype', '*SqlQuery'),
            FilterMatch::where('datatype', '*DirectorObject'),
            FilterMatch::where('datatype', '*Dictionary')
        );

        $datafieldQuery = $datafieldQuery->addFilter(Filter::not($filter));
        $unsupportedTypeQuery->addFilter($filter);

        $supportedTypeQuery = clone $datafieldQuery;
        $supportedTypeQuery->joinLeft(['dds' => 'director_datafield_setting'], "dd.id = dds.datafield_id AND dds.setting_name = 'visibility'", []);
        $protectedDatafieldsQuery = clone $supportedTypeQuery;
        $protectedDatafieldsQuery->addFilter(Filter::matchAll(
            FilterMatch::where('dd.datatype', '*String'),
            Filter::matchAll(
                FilterMatch::where('dds.setting_name', 'visibility'),
                FilterMatch::where('dds.setting_value', 'hidden')
            )
        ));

        $supportedTypeQuery->addFilter(Filter::not($protectedDatafieldsQuery->getFilter()));
        $supportedTypeQuery->select()
            ->having('count = 1');

        $duplicateFieldsQuery->select()->having('count > 1');

        $supportedTypeQuery->addFilter(Filter::fromQueryString('category_id IS NULL'));
        $categoryFieldsQuery->addFilter(Filter::fromQueryString('category_id IS NOT NULL'));

        $supportedTypeQuery->addFilter(Filter::matchAll($datafieldQuery->getFilter()));

        $typeOffset = strlen("Icinga\Module\Director\DataType\DataType");
        // Dry run summary
        if ($this->params->get('dry-run')) {
            printf("The following datafield types and the corresponding number of datafields can be migrated:\n");
            $total = 0;
            foreach (
                $db->select()->from(
                    ['q' =>  new DbSelectParenthesis($supportedTypeQuery->getSelectQuery())],
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

            printf("The following number of datafields are protected and can not be migrated: %d\n\n", $protectedDatafieldsQuery->count());
            printf("The following number of datafields belong to a category and can not be migrated: %d\n\n", $categoryFieldsQuery->count());

            printf("The following datafield types and the corresponding number of datafields can not be migrated:\n");
            $total = 0;
            foreach ($unsupportedTypeQuery->group('datatype') as $row) {
                printf("Data type: %s | count: %d\n", substr($row->datatype, $typeOffset), $row->count);
                $total += $row->count;
            }

            printf("Total datafields that can not be migrated because of incompatible datatypes with new custom property support: %d\n\n", $total);

            printf("The following datafields can not be migrated as there are duplicates:\n");
            $total = 0;
            foreach ($duplicateFieldsQuery->group('varname') as $row) {
                printf("Var name: %s | count: %d\n", $row->varname, $row->count);
                $total += $row->count;
            }

            printf("Total datafields that can not be migrated because of having duplicates: %d\n", $total);
        }

        $directorProperty = DirectorProperty::loadAll(
            $db,
            $db->getDbAdapter()->select()->from('director_property')->where('parent_uuid IS NULL'),
            'key_name'
        );

        $alreadyExistingProperties = [];
        $simpleCustomProperties = [];
        $customPropertiesWithItems = [];
        foreach ($supportedTypeQuery as $row) {
            if (isset($directorProperty[$row->varname])) {
                $alreadyExistingProperties[] = $row->varname;

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
                echo "[-] Skipping migration of datafield '$row->varname' as it has an unsupported datatype '$dataType'\n";
                continue;
            }

            if ($dataType === 'array' || $dataType === 'datalist') {
                $customPropertiesWithItems[$row->varname] = $customProperty;
            } else {
                $simpleCustomProperties[$row->varname] = $customProperty;
            }
        }

        if ($this->params->get('dry-run')) {
            printf(
                "Total datafields that can not be migrated as the custom properties with the same name already"
                . " exists: %d\n",
                count($alreadyExistingProperties)
            );

            return;
        }

        echo "Migrating Data fields\n";
        if ($this->isVerbose && ! empty( $alreadyExistingProperties)) {
            foreach ($alreadyExistingProperties as $varname) {
                printf("[-] Skipped migrating datafield '%s' as a custom property with the same name already exists\n", $varname);
            }
        }

        if (! empty($customPropertiesWithItems) || ! empty($simpleCustomProperties)) {
            $db->getDbAdapter()->beginTransaction();

            foreach ($simpleCustomProperties as $varName => $customProperty) {
                if ($this->isVerbose) {
                    echo "[+] Datafield '$varName' successfully migrated\n";
                }

                $db->insert('director_property', $customProperty);
            }

            foreach ($customPropertiesWithItems as $varName => $customProperty) {
                $itemType = $customProperty['item_type'];
                unset($customProperty['item_type']);
                $db->insert('director_property', $customProperty);
                $db->insert('director_property', [
                    'uuid' => Uuid::uuid4()->getBytes(),
                    'key_name' => 0,
                    'value_type' => $itemType,
                    'parent_uuid' => $customProperty['uuid']
                ]);

                if ($this->isVerbose) {
                    echo "[+] Datafield '$varName' successfully migrated\n";
                }
            }

            $db->getDbAdapter()->commit();
        }

        echo "Migration completed\n";

        $totalMigrated = count($simpleCustomProperties) + count($customPropertiesWithItems);
        $totalSkipped = count(DirectorDatafield::loadAll($db)) - $totalMigrated;

        echo "Summary:\n";
        printf("Total datafields migrated: %d\n", $totalMigrated);
        printf("Total datafields skipped: %d\n", $totalSkipped);
    }
}
