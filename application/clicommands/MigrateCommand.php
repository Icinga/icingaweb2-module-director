<?php

namespace Icinga\Module\Director\Clicommands;

use Icinga\Data\Db\DbQuery;
use Icinga\Data\Filter\Filter;
use Icinga\Data\Filter\FilterMatch;
use Icinga\Module\Director\Cli\Command;
use Icinga\Module\Director\Db\DbSelectParenthesis;
use Icinga\Module\Director\Db\DbUtil;
use Icinga\Module\Director\Objects\DirectorDatafield;
use Icinga\Module\Director\Objects\DirectorDatalist;
use Icinga\Module\Director\Objects\DirectorProperty;
use PDO;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

/**
 * Handle DB migrations
 *
 * This command retrieves information about unapplied database migration and
 * helps applying them.
 */
class MigrateCommand extends Command
{
    /**
     * Datatype suffixes (after 'Icinga\Module\Director\DataType\DataType') that are supported
     * for migration.
     */
    private const SUPPORTED_DATATYPES = ['Array', 'Boolean', 'Number', 'String', 'Datalist'];

    private $existingCustomProperties = [];

    /** @var array Migrated Datafields ['id' => 'datafield_name'] */
    private $migratedDataFields = [];

    /**
     * Show what would be migrated, without making any changes
     *
     * USAGE
     *
     * icingacli director migrate summary
     */
    public function summaryAction()
    {
        $customPropertiesToMigrate = $this->prepareCustomProperties();
        $this->printMigrationDetails($customPropertiesToMigrate);

        $totalMigrated = 0;
        foreach ($customPropertiesToMigrate as $customProperty) {
            if (! str_starts_with($customProperty['value_type'], 'unsupported-')) {
                $totalMigrated++;
            }
        }

        $totalSkipped = count(DirectorDatafield::loadAll($this->db())) - $totalMigrated;

        echo "Summary:\n";
        printf("Total number of datafields that will be migrated: %d\n", $totalMigrated);
        printf("Total number of datafields that will be skipped: %d\n", $totalSkipped);
    }

    /**
     * Run datafield migration
     *
     * USAGE
     *
     * icingacli director migrate datafields --dry-run --delete --verbose
     *
     * OPTIONS
     *
     *  --dry-run    Preview what would be migrated without writing to the database
     *
     *  --delete     Remove original datafield records and their bindings after migration (skipped with --dry-run)
     *
     *  --verbose    Show detailed migration results
     */
    public function datafieldsAction()
    {
        $db = $this->db();
        $customPropertiesToMigrate = $this->prepareCustomProperties();
        $dryRun = $this->params->shift('dry-run') ?? false;
        $delete = $this->params->shift('delete') ?? false;
        // Dry run summary
        if ($dryRun) {
            $this->printMigrationDetails($customPropertiesToMigrate);
        }

        echo "Migrating Data fields\n";

        foreach ($this->existingCustomProperties as $varname) {
            unset($customPropertiesToMigrate[$varname]);

            if ($this->isVerbose) {
                echo "[-] Skipping migrating datafield '$varname' as a custom variable"
                . " with the same name already exists\n";
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

            foreach ($this->getDatafieldsWithDuplicateNames() as $varname => $count) {
                printf(
                    "[-] Skipping migrating datafield '%s' as there are '%d' datafields with same name\n",
                    $varname,
                    $count
                );
            }
        }

        if (! empty($customPropertiesToMigrate)) {
            $this->migrateDatafields($customPropertiesToMigrate, $dryRun, $delete && ! $dryRun);
        }

        echo "Migration completed\n";

        $totalMigrated = 0;
        foreach ($customPropertiesToMigrate as $customProperty) {
            if (! str_starts_with($customProperty['value_type'], 'unsupported-')) {
                $totalMigrated++;
            }
        }

        $totalSkipped = count(DirectorDatafield::loadAll($db)) - $totalMigrated;
        if ($delete) {
            echo "The following datafields have been migrated and deleted:\n";
            if ($this->isVerbose) {
                foreach ($this->migratedDataFields as $dataField) {
                    echo "$dataField \n";
                }
            }
        }

        echo "Summary:\n";
        printf("Total number of datafields migrated: %d\n", $totalMigrated);
        printf("Total number of datafields skipped: %d\n", $totalSkipped);
    }

    /**
     * Print the datatype/category/duplicate breakdown, and reconcile it against
     * datafields that are skipped because a custom property with the same name
     * already exists, so the final migrated/skipped totals aren't a mystery.
     *
     * @param array $customPropertiesToMigrate
     *
     * @return void
     */
    private function printMigrationDetails(array $customPropertiesToMigrate): void
    {
        $this->checkMigrateableDatafieldTypes();
        $this->checkDatafieldsWithCategory();
        $this->checkUnmigrateableDatafieldTypes();
        $this->checkDatafieldsWithDuplicateNames();

        $supportedDatatypeCount = count($customPropertiesToMigrate) + count($this->existingCustomProperties);
        printf(
            "Of the %d datafields with a supported datatype, %d already have a matching new custom variable"
            . " and will be skipped\n\n",
            $supportedDatatypeCount,
            count($this->existingCustomProperties)
        );
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
                'datafield_id' => $row->id,
                'uuid' => Uuid::uuid4()->getBytes(),
                'key_name' => $row->varname,
                'label' => $row->caption,
                'description' => $row->description,
                'category_id' => $row->category_id
            ];
            $dataType = strtolower(substr($row->datatype, $typeOffset));

            if ($dataType === 'array') {
                $customProperty['value_type'] = 'dynamic-array';
                $customProperty['item_type'] = 'string';
            } elseif ($dataType === 'boolean' || $dataType === 'number') {
                $customProperty['value_type'] = $dataType === 'boolean' ? 'bool' : $dataType;
            } elseif ($dataType === 'string') {
                $settings = DirectorDatafield::load($row->id, $db)->getSettings();
                $customProperty['value_type'] = ($settings['visibility'] ?? null) === 'hidden'
                    ? 'sensitive'
                    : 'string';
            } elseif ($dataType === 'datalist') {
                $datalist = DirectorDatafield::load($row->id, $db);
                $settings = $datalist->getSettings();
                $behaviour = $settings['behavior'] ?? 'strict';
                if ($behaviour === 'strict' || $behaviour === 'suggest_strict') {
                    $customProperty['value_type'] = 'datalist-strict';
                } else {
                    $customProperty['value_type'] = 'datalist-non-strict';
                }

                $customProperty['item_type'] = $settings['data_type'] === 'array'
                    ? 'dynamic-array'
                    : 'string';

                if (isset($settings['datalist_id'])) {
                    $customProperty['datalist_uuid'] = DirectorDatalist::loadWithAutoIncId(
                        $settings['datalist_id'],
                        $db
                    )->get('uuid');
                }
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
     * When $delete is set, the legacy datafield bindings and definitions are removed
     * as part of the very same transaction as the migration, so a failure on either
     * side rolls back both instead of leaving migrated properties next to a partially
     * deleted legacy datafield.
     *
     * @param array $customProperties
     *
     * @return void
     */
    private function migrateDatafields(array $customProperties, bool $dryRun, bool $delete = false): void
    {
        $db = $this->db();

        $migrate = function () use ($db, $customProperties, $dryRun) {
            $dbAdapter = $db->getDbAdapter();
            foreach ($customProperties as $varName => $customProperty) {
                if (str_starts_with($customProperty['value_type'], 'unsupported-')) {
                    if ($this->isVerbose) {
                        echo "[-] Skipping migration of datafield '$varName' as it has an unsupported datatype '"
                            . substr($customProperty['value_type'], strlen('unsupported-'))
                            . "'\n";
                    }

                    continue;
                }

                $itemType = null;
                if (isset($customProperty['item_type'])) {
                    $itemType = $customProperty['item_type'];
                    unset($customProperty['item_type']);
                }

                $datalistUuidBytes = null;
                if (isset($customProperty['datalist_uuid'])) {
                    $datalistUuidBytes = $customProperty['datalist_uuid'];
                    unset($customProperty['datalist_uuid']);
                }

                if (! $dryRun) {
                    $datafieldId = $customProperty['datafield_id'];
                    unset($customProperty['datafield_id']);
                    $this->migratedDataFields[$datafieldId] = $varName;
                    $uuidBytes = $customProperty['uuid'];
                    $customProperty['uuid'] = DbUtil::quoteBinaryCompat($uuidBytes, $dbAdapter);
                    $db->insert('director_property', $customProperty);
                    $propertyUuid = Uuid::fromBytes($uuidBytes);

                    if ($itemType !== null) {
                        $childUuidBytes = Uuid::uuid4()->getBytes();
                        $db->insert('director_property', [
                            'uuid' => DbUtil::quoteBinaryCompat($childUuidBytes, $dbAdapter),
                            'key_name' => 0,
                            'value_type' => $itemType,
                            'parent_uuid' => DbUtil::quoteBinaryCompat($uuidBytes, $dbAdapter)
                        ]);
                    }

                    if ($datalistUuidBytes !== null) {
                        $db->insert('director_property_datalist', [
                            'property_uuid' => DbUtil::quoteBinaryCompat($uuidBytes, $dbAdapter),
                            'list_uuid' => DbUtil::quoteBinaryCompat($datalistUuidBytes, $dbAdapter)
                        ]);
                    }

                    $this->migrateDatafieldObjectTemplateBinding($datafieldId, $propertyUuid, $varName);
                }

                if ($dryRun) {
                    echo "[*] Would migrate datafield '$varName'\n";
                } elseif ($this->isVerbose) {
                    echo "[+] Datafield '$varName' successfully migrated\n";
                }
            }
        };

        if ($dryRun) {
            $migrate();

            return;
        }

        if ($delete) {
            $db->runFailSafeTransaction(function () use ($migrate) {
                $migrate();
                $this->deleteMigratedDataFields();
            });
        } else {
            $db->runFailSafeTransaction($migrate);
        }
    }

    /**
     * Check which datafield types are supported by the new custom variable support
     *
     * This does not yet account for datafields that are skipped because a custom
     * property with the same name already exists
     *
     * @return void
     */
    private function checkMigrateableDatafieldTypes(): void
    {
        $db = $this->db();
        printf(
            "The following datafield types and the corresponding number of datafields"
            . " have a supported datatype:\n"
        );
        $total = 0;
        $query = $this->getDataFieldsMigrationQuery();
        $typeOffset = strlen("Icinga\Module\Director\DataType\DataType");
        foreach (
            $db->select()->from(
                ['q' =>  new DbSelectParenthesis($query->getSelectQuery())],
                ['datatype', 'count_q' => 'COUNT(*)']
            )->group('datatype') as $row
        ) {
            printf(
                "Data type: %s | count: %d\n",
                substr($row->datatype, $typeOffset),
                $row->count_q
            );
            $total += $row->count_q;
        }

        printf("Total datafields with a supported datatype: %d\n\n", $total);
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
        $typeOffset = strlen("Icinga\Module\Director\DataType\DataType");
        foreach ($this->getDatafieldsWithUnsupportedValuetype() as $varname => $datatype) {
            $groupByDataType[$datatype][] = $varname;
            $total++;
        }

        foreach ($groupByDataType as $datatype => $datafields) {
            printf("Data type: %s | count: %d\n", substr($datatype, $typeOffset), count($datafields));
        }

        if ($total > 0) {
            printf(
                "Total datafields that can not be migrated because of incompatible datatypes"
                . " with new custom variable support: %d\n\n",
                $total
            );
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
            $this->getDatafieldsWithCategory()
        );

        if (! empty($skippedFields)) {
            $query->addFilter(Filter::not(Filter::where('varname', $skippedFields)));
        }

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
                    'category_id' => 'dd.category_id',
                ]
            );
    }

    /**
     * Get datafields with unsupported value type in new custom variable support
     *
     * A datafield is unsupported unless its datatype is handled explicitly by
     * prepareCustomProperties(); keep self::SUPPORTED_DATATYPES in sync with the
     * types handled there so migrateable/unmigrateable counts never drift apart.
     *
     * @return array
     */
    private function getDatafieldsWithUnsupportedValuetype()
    {
        $query = $this->getDataFieldQuery();
        $supportedFilters = [];
        foreach (self::SUPPORTED_DATATYPES as $suffix) {
            $supportedFilters[] = FilterMatch::where('datatype', "*$suffix");
        }

        $query->addFilter(Filter::not(Filter::matchAny($supportedFilters)));
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
        $query->columns(['varname' => 'dd.varname', 'count' => 'COUNT(dd.varname)']);
        $query->group('dd.varname');
        $query->select()->having('COUNT(dd.varname) > 1');

        return $query->fetchPairs();
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

    /**
     * Migrate the binding of the datafield-to-object bindings
     *
     * @param int           $datafieldId
     * @param UuidInterface $propertyUuid
     *
     * @return void
     */
    private function migrateDatafieldObjectTemplateBinding(
        int $datafieldId,
        UuidInterface $propertyUuid,
        string $varName
    ): void {
        $db = $this->db();
        $dbAdapter = $db->getDbAdapter();
        $propertyUuidExpr = DbUtil::quoteBinaryCompat($propertyUuid->getBytes(), $dbAdapter);
        $objectTypes = ['host', 'service', 'notification', 'command', 'user'];
        foreach ($objectTypes as $type) {
            $query = $dbAdapter->select()->from(['io' => "icinga_{$type}"], ['uuid'])
                ->join(['iof' => "icinga_{$type}_field"], "io.id = iof.{$type}_id", ['is_required', 'var_filter'])
                ->where('iof.datafield_id = ?', $datafieldId);

            foreach ($dbAdapter->fetchAll($query, fetchMode: PDO::FETCH_ASSOC) as $row) {
                if (! empty($row['var_filter'])) {
                    echo "[!] Datafield '$varName' has a var_filter set for its icinga_{$type} binding; "
                        . "var_filter is not supported by the new property system and will not be migrated\n";
                }

                $db->insert(
                    "icinga_{$type}_property",
                    [
                        'property_uuid' => $propertyUuidExpr,
                        "{$type}_uuid"  => DbUtil::quoteBinaryCompat(
                            DbUtil::binaryResult($row['uuid']),
                            $dbAdapter
                        ),
                        'required' => $row['is_required'],
                    ]
                );
            }
        }
    }

    /**
     * Delete the migrated datafields
     *
     * Must be called from within a transaction, it performs multiple deletes that
     * are only consistent with each other and with the migration when committed
     * or rolled back together.
     *
     * @return void
     */
    private function deleteMigratedDataFields(): void
    {
        if (empty($this->migratedDataFields)) {
            return;
        }

        $db = $this->db();
        $objectTypes = ['host', 'service', 'notification', 'command', 'user'];
        $datafieldIds = array_keys($this->migratedDataFields);
        foreach ($objectTypes as $type) {
            $db->delete(
                "icinga_{$type}_field",
                Filter::where('datafield_id', $datafieldIds)
            );
        }

        $db->delete(
            'director_datafield',
            Filter::where('id', $datafieldIds)
        );
    }
}
