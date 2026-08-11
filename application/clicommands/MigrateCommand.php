<?php

namespace Icinga\Module\Director\Clicommands;

use Icinga\Data\Db\DbQuery;
use Icinga\Data\Filter\Filter;
use Icinga\Data\Filter\FilterMatch;
use Icinga\Module\Director\Cli\Command;
use Icinga\Module\Director\CustomVariable\CustomVariableValueCleaner;
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

    /**
     * Show what would be migrated, without making any changes
     *
     * USAGE
     *
     * icingacli director migrate summary
     */
    public function summaryAction()
    {
        [$customPropertiesToMigrate, $existingCustomProperties] = $this->prepareCustomProperties();
        $this->printMigrationDetails($customPropertiesToMigrate, $existingCustomProperties);

        // same check a real run does, so a field held back by a filter doesn't
        // count as migrated here either. no output of its own, printMigrationDetails()
        // above already covers that
        [$migratedDataFields] = $this->migrateDatafields($customPropertiesToMigrate, true, false, false, false);
        $totalMigrated = count($migratedDataFields);

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
     * icingacli director migrate datafields --dry-run --delete --allow-lossy-filters --verbose
     *
     * OPTIONS
     *
     *  --dry-run              Preview what would be migrated without writing to the database
     *
     *  --delete               Remove original datafield records and their bindings after migration
     *                         (skipped with --dry-run)
     *
     *  --allow-lossy-filters  Migrate a binding even if it has a var_filter, dropping the filter.
     *                         By default such bindings and their datafield are left alone.
     *
     *  --verbose              Show detailed migration results
     */
    public function datafieldsAction()
    {
        $db = $this->db();
        [$customPropertiesToMigrate, $existingCustomProperties] = $this->prepareCustomProperties();
        // count datafields now, before delete wipes the migrated rows away
        $totalDatafields = count(DirectorDatafield::loadAll($db));
        $dryRun = $this->params->shift('dry-run') ?? false;
        $delete = $this->params->shift('delete') ?? false;
        $allowLossyFilters = $this->params->shift('allow-lossy-filters') ?? false;
        // Dry run summary
        if ($dryRun) {
            $this->printMigrationDetails($customPropertiesToMigrate, $existingCustomProperties);
        }

        echo "Migrating Data fields\n";

        foreach ($existingCustomProperties as $varname) {
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

            foreach ($this->getDatafieldsWithDuplicateNames() as $varname => $count) {
                printf(
                    "[-] Skipping migrating datafield '%s' as there are '%d' datafields with same name\n",
                    $varname,
                    $count
                );
            }
        }

        $migratedDataFields = [];
        $retainedDataFields = [];
        if (! empty($customPropertiesToMigrate)) {
            [$migratedDataFields, $retainedDataFields] = $this->migrateDatafields(
                $customPropertiesToMigrate,
                $dryRun,
                $delete && ! $dryRun,
                $allowLossyFilters
            );
        }

        echo "Migration completed\n";

        // a field held back by a filter lands in $retainedDataFields, not here,
        // counting it as migrated too would also shrink the skipped total below
        $totalMigrated = count($migratedDataFields);

        $totalSkipped = $totalDatafields - $totalMigrated;
        if ($delete) {
            if ($dryRun) {
                echo "The following datafields would be migrated and deleted:\n";
                if ($this->isVerbose) {
                    foreach ($migratedDataFields as $dataField) {
                        echo "$dataField \n";
                    }
                }

                if (! empty($retainedDataFields)) {
                    echo "The following datafields would be left untouched, as one or more of their bindings"
                        . " has a var_filter that needs --allow-lossy-filters to migrate:\n";
                    if ($this->isVerbose) {
                        foreach ($retainedDataFields as $dataField) {
                            echo "$dataField \n";
                        }
                    }
                }
            } else {
                echo "The following datafields have been migrated and deleted:\n";
                if ($this->isVerbose) {
                    foreach ($migratedDataFields as $dataField) {
                        echo "$dataField \n";
                    }
                }

                if (! empty($retainedDataFields)) {
                    echo "The following datafields were left untouched, as one or more of their bindings"
                        . " has a var_filter that needs --allow-lossy-filters to migrate:\n";
                    if ($this->isVerbose) {
                        foreach ($retainedDataFields as $dataField) {
                            echo "$dataField \n";
                        }
                    }
                }
            }
        }

        echo "Summary:\n";
        printf("Total number of datafields migrated: %d\n", $totalMigrated);
        printf("Total number of datafields skipped: %d\n", $totalSkipped);
    }

    /**
     * Print the datatype/duplicate breakdown, and reconcile it against
     * datafields that are skipped because a custom property with the same name
     * already exists, so the final migrated/skipped totals aren't a mystery.
     *
     * @param array $customPropertiesToMigrate
     * @param array $existingCustomProperties
     *
     * @return void
     */
    private function printMigrationDetails(array $customPropertiesToMigrate, array $existingCustomProperties): void
    {
        $this->checkMigrateableDatafieldTypes();
        $this->checkUnmigrateableDatafieldTypes();
        $this->checkDatafieldsWithDuplicateNames();

        $supportedDatatypeCount = count($customPropertiesToMigrate) + count($existingCustomProperties);
        printf(
            "Of the %d datafields with a supported datatype, %d already have a matching new custom variable"
            . " and will be skipped\n\n",
            $supportedDatatypeCount,
            count($existingCustomProperties)
        );
    }

    /**
     * Prepare custom properties to migrate
     *
     * @return array{0: array, 1: array} [$customPropertiesToMigrate, $existingCustomProperties]
     */
    private function prepareCustomProperties(): array
    {
        $db = $this->db();
        $directorProperty = DirectorProperty::loadAll(
            $db,
            $db->getDbAdapter()->select()->from('director_property')->where('parent_uuid IS NULL'),
            'key_name'
        );

        // key_name is case-insensitive in the new schema (citext/utf8mb4_unicode_ci),
        // but a plain PHP array lookup on $directorProperty is not, so a legacy
        // datafield differing only by case would slip past this check and blow up
        // later on the database's own unique constraint instead.
        $existingKeyNamesByLower = [];
        foreach (array_keys($directorProperty) as $keyName) {
            $existingKeyNamesByLower[mb_strtolower($keyName, 'UTF-8')] = true;
        }

        $customProperties = [];
        $existingCustomProperties = [];
        $migrationQuery = $this->getDataFieldsMigrationQuery();
        $typeOffset = strlen("Icinga\Module\Director\DataType\DataType");
        foreach ($migrationQuery as $row) {
            if (isset($existingKeyNamesByLower[mb_strtolower($row->varname, 'UTF-8')])) {
                $existingCustomProperties[] = $row->varname;

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

                // older datalists were saved without this setting, and it defaulted to string
                $customProperty['item_type'] = ($settings['data_type'] ?? 'string') === 'array'
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

        return [$customProperties, $existingCustomProperties];
    }

    /**
     * Migrate given prepared custom properties
     *
     * With $delete, legacy bindings and definitions are removed in the same transaction as
     * the migration, so a failure on either side rolls back both. A datafield with a binding
     * that has a var_filter we're not allowed to drop is left completely untouched, no property
     * gets created for it either, or a later run with --allow-lossy-filters would find that
     * property already there and skip the datafield for good. Old values also get stamped with
     * the new property's UUID, unless the datafield was left untouched this way.
     *
     * @param array $customProperties
     * @param bool  $allowLossyFilters Migrate a filtered binding anyway, dropping the var_filter
     * @param bool  $reportProgress    Print per-field lines, off when a caller already prints its
     *                                 own summary and doesn't need every field listed twice
     *
     * @return array{0: array, 1: array} [$migratedDataFields, $retainedDataFields], both as
     *         ['id' => 'datafield_name']. Filled on a dry run too, since the var_filter
     *         classification runs either way, only the actual writes are skipped.
     *         $retainedDataFields never overlaps with $migratedDataFields, it's the datafields
     *         left untouched because of a filter we can't drop.
     */
    private function migrateDatafields(
        array $customProperties,
        bool $dryRun,
        bool $delete = false,
        bool $allowLossyFilters = false,
        bool $reportProgress = true
    ): array {
        $db = $this->db();
        $cleaner = new CustomVariableValueCleaner($db);
        $migratedDataFields = [];
        $retainedDataFields = [];

        $migrate = function () use (
            $db,
            $cleaner,
            $customProperties,
            $dryRun,
            $allowLossyFilters,
            $reportProgress,
            &$migratedDataFields,
            &$retainedDataFields
        ) {
            $dbAdapter = $db->getDbAdapter();
            foreach ($customProperties as $varName => $customProperty) {
                if (str_starts_with($customProperty['value_type'], 'unsupported-')) {
                    if ($reportProgress && $this->isVerbose) {
                        echo "[-] Skipping migration of datafield '$varName' as it has an unsupported datatype '"
                            . substr($customProperty['value_type'], strlen('unsupported-'))
                            . "'\n";
                    }

                    continue;
                }

                $datafieldId = $customProperty['datafield_id'];

                if ($this->hasUnmigratableFilterBinding($datafieldId, $varName, $allowLossyFilters, $reportProgress)) {
                    $retainedDataFields[$datafieldId] = $varName;

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

                $uuidBytes = $customProperty['uuid'];
                $propertyUuid = Uuid::fromBytes($uuidBytes);
                $migratedDataFields[$datafieldId] = $varName;

                if (! $dryRun) {
                    unset($customProperty['datafield_id']);
                    $customProperty['uuid'] = DbUtil::quoteBinaryCompat($uuidBytes, $dbAdapter);
                    $db->insert('director_property', $customProperty);

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
                }

                $this->migrateDatafieldObjectTemplateBinding(
                    $datafieldId,
                    $dryRun ? null : $propertyUuid,
                    $varName,
                    $allowLossyFilters,
                    $dryRun
                );

                if (! $dryRun) {
                    // old values had no UUID, stamp them now or detach won't find them later
                    $cleaner->backfillPropertyUuid($varName, $propertyUuid);
                }

                if ($reportProgress) {
                    if ($dryRun) {
                        echo "[*] Would migrate datafield '$varName'\n";
                    } elseif ($this->isVerbose) {
                        echo "[+] Datafield '$varName' successfully migrated\n";
                    }
                }
            }
        };

        if ($dryRun) {
            $migrate();

            return [$migratedDataFields, $retainedDataFields];
        }

        if ($delete) {
            $db->runFailSafeTransaction(function () use ($migrate, &$migratedDataFields) {
                $migrate();
                $this->deleteMigratedDataFields($migratedDataFields);
            });
        } else {
            $db->runFailSafeTransaction($migrate);
        }

        return [$migratedDataFields, $retainedDataFields];
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
            array_keys($this->getDatafieldsWithUnsupportedValuetype())
        );

        if (! empty($skippedFields)) {
            $query->addFilter(Filter::not(Filter::where('varname', $skippedFields)));
        }

        return $query;
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
        $query->columns(['varname' => 'dd.varname']);

        // key_name is case-insensitive in the new schema, but the legacy varname column
        // is not, so this groups by lowercase name in PHP rather than relying on a SQL
        // GROUP BY, which would follow the datafield table's own case-sensitive collation
        // and miss names that only differ by case.
        $varnamesByLower = [];
        foreach ($query->fetchColumn() as $varname) {
            $varnamesByLower[mb_strtolower($varname, 'UTF-8')][] = $varname;
        }

        $duplicates = [];
        foreach ($varnamesByLower as $varnames) {
            if (count($varnames) > 1) {
                foreach ($varnames as $varname) {
                    $duplicates[$varname] = count($varnames);
                }
            }
        }

        return $duplicates;
    }

    /**
     * Check if any of this datafield's bindings has a var_filter we can't drop
     *
     * The new property system has no var_filter equivalent, so migrating a filtered binding
     * would turn a conditional requirement into an unconditional one. Runs regardless of
     * $dryRun, a preview needs to report the same retained set a real run would produce.
     *
     * This is a read only check, done before touching the database, so a datafield we end up
     * leaving alone never gets a director_property row in the first place. Otherwise a later
     * run with --allow-lossy-filters would find that property already there and skip the
     * datafield for good, without ever migrating its filtered binding.
     */
    private function hasUnmigratableFilterBinding(
        int $datafieldId,
        string $varName,
        bool $allowLossyFilters,
        bool $reportProgress = true
    ): bool {
        if ($allowLossyFilters) {
            return false;
        }

        $dbAdapter = $this->db()->getDbAdapter();
        $objectTypes = ['host', 'service', 'notification', 'command', 'user'];
        foreach ($objectTypes as $type) {
            $query = $dbAdapter->select()->from(['io' => "icinga_{$type}"], [])
                ->join(['iof' => "icinga_{$type}_field"], "io.id = iof.{$type}_id", ['var_filter'])
                ->where('iof.datafield_id = ?', $datafieldId);

            foreach ($dbAdapter->fetchCol($query) as $varFilter) {
                if (! empty($varFilter)) {
                    if ($reportProgress) {
                        echo "[!] Datafield '$varName' has a var_filter set for its icinga_{$type} binding; "
                            . "var_filter is not supported by the new property system, so it will not be"
                            . " migrated or deleted\n";
                    }

                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Migrate the binding of the datafield-to-object bindings
     *
     * Only called once we already know none of this datafield's bindings has a var_filter
     * we're not allowed to drop, see hasUnmigratableFilterBinding(). $allowLossyFilters is only
     * used here to decide whether to warn about a filter getting dropped along the way.
     *
     * @param int                $datafieldId
     * @param UuidInterface|null $propertyUuid Unused, and may be null, when $dryRun is true
     * @param bool               $allowLossyFilters
     * @param bool               $dryRun
     *
     * @return void
     */
    private function migrateDatafieldObjectTemplateBinding(
        int $datafieldId,
        ?UuidInterface $propertyUuid,
        string $varName,
        bool $allowLossyFilters,
        bool $dryRun
    ): void {
        $db = $this->db();
        $dbAdapter = $db->getDbAdapter();
        $propertyUuidExpr = $dryRun ? null : DbUtil::quoteBinaryCompat($propertyUuid->getBytes(), $dbAdapter);
        $objectTypes = ['host', 'service', 'notification', 'command', 'user'];
        foreach ($objectTypes as $type) {
            $query = $dbAdapter->select()->from(['io' => "icinga_{$type}"], ['uuid'])
                ->join(['iof' => "icinga_{$type}_field"], "io.id = iof.{$type}_id", ['is_required', 'var_filter'])
                ->where('iof.datafield_id = ?', $datafieldId);

            foreach ($dbAdapter->fetchAll($query, fetchMode: PDO::FETCH_ASSOC) as $row) {
                if (! empty($row['var_filter']) && $allowLossyFilters) {
                    echo "[!] Datafield '$varName' has a var_filter set for its icinga_{$type} binding; "
                        . "var_filter is not supported by the new property system, so it will be dropped"
                        . " and the binding will be migrated without it\n";
                }

                if ($dryRun) {
                    continue;
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
     * @param array $migratedDataFields Datafields to delete, as ['id' => 'datafield_name']. Must
     *                                  already exclude datafields with a retained var_filter binding.
     *
     * @return void
     */
    private function deleteMigratedDataFields(array $migratedDataFields): void
    {
        if (empty($migratedDataFields)) {
            return;
        }

        $db = $this->db();
        $objectTypes = ['host', 'service', 'notification', 'command', 'user'];
        $datafieldIds = array_keys($migratedDataFields);
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
