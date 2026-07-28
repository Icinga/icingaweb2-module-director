<?php

// SPDX-FileCopyrightText: 2026 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Tests\Icinga\Module\Director\Objects;

use Icinga\Module\Director\Db;
use Icinga\Module\Director\Db\DbUtil;
use Icinga\Module\Director\Objects\DirectorDatafield;
use Icinga\Module\Director\Objects\DirectorDatafieldCategory;
use Icinga\Module\Director\Objects\DirectorDatalist;
use Icinga\Module\Director\Objects\IcingaHost;
use Icinga\Module\Director\Objects\IcingaHostField;
use Icinga\Module\Director\Test\BaseTestCase;
use Ramsey\Uuid\Uuid;
use Tests\Icinga\Module\Director\Objects\Lib\TestableMigrateCommand;

class MigrateCommandTest extends BaseTestCase
{
    private const PREFIX = '___TEST___';

    // Migratable datafield varnames
    private const VAR_ENV            = self::PREFIX . 'env';

    private const VAR_HTTP_VHOSTS    = self::PREFIX . 'http_vhosts';

    private const VAR_CHECK_INTERVAL = self::PREFIX . 'check_interval';

    private const VAR_ENV_CHOICES    = self::PREFIX . 'env_choices';

    private const VAR_ENV_SUGGEST    = self::PREFIX . 'env_suggest';

    private const VAR_ENV_CHOICES_DEFAULT_BEHAVIOR = self::PREFIX . 'env_choices_default_behavior';

    // Non-migratable datafield varnames
    private const VAR_SQL_QUERY    = self::PREFIX . 'sql_query_field';

    private const VAR_CATEGORIZED  = self::PREFIX . 'categorized_field';

    // Migratable as 'sensitive' (legacy hidden-visibility string)
    private const VAR_HIDDEN       = self::PREFIX . 'snmp_community';

    private const VAR_DUP          = self::PREFIX . 'notification_email';

    private const VAR_TIME_FIELD = self::PREFIX . 'time_field';

    // Case-only duplicate pair (skip both, since key_name is case-insensitive)
    private const VAR_CASE_DUP_LOWER = self::PREFIX . 'region';

    private const VAR_CASE_DUP_UPPER = self::PREFIX . 'Region';

    // Collides with a pre-existing property that only differs in case
    private const VAR_CASE_EXISTING = self::PREFIX . 'Datacenter';

    private const LIST_NAME = self::PREFIX . 'migrate_list';

    private const CAT_NAME  = self::PREFIX . 'migrate_category';

    private const HOST_NAME = self::PREFIX . 'binding_host';

    private const MIGRATABLE = [
        self::VAR_ENV,
        self::VAR_HTTP_VHOSTS,
        self::VAR_CHECK_INTERVAL,
        self::VAR_ENV_CHOICES,
        self::VAR_ENV_SUGGEST,
        self::VAR_HIDDEN,
        self::VAR_ENV_CHOICES_DEFAULT_BEHAVIOR,
    ];

    private const ALL_TEST_VARS = [
        self::VAR_ENV,
        self::VAR_HTTP_VHOSTS,
        self::VAR_CHECK_INTERVAL,
        self::VAR_ENV_CHOICES,
        self::VAR_ENV_SUGGEST,
        self::VAR_ENV_CHOICES_DEFAULT_BEHAVIOR,
        self::VAR_SQL_QUERY,
        self::VAR_CATEGORIZED,
        self::VAR_HIDDEN,
        self::VAR_DUP,
        self::VAR_TIME_FIELD,
        self::VAR_CASE_DUP_LOWER,
        self::VAR_CASE_DUP_UPPER,
        self::VAR_CASE_EXISTING,
        self::PREFIX . 'tls_cert_path',
        self::PREFIX . 'tls_key_path',
    ];

    public function testDryRunPrintsWhatWouldMigrateWithoutWriting(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();
        $this->createAllFixtures($db);

        $cmd = new TestableMigrateCommand($db, ['--dry-run']);
        $output = $cmd->runDatafields();

        foreach (self::MIGRATABLE as $varname) {
            $this->assertStringContainsString(
                $varname,
                $output,
                "Dry-run output must list '$varname' as migratable"
            );
        }

        $dba = $db->getDbAdapter();
        foreach (self::MIGRATABLE as $varname) {
            $count = $dba->fetchOne(
                $dba->select()->from('director_property', ['cnt' => 'COUNT(*)'])->where('key_name = ?', $varname)
            );

            $this->assertEquals(0, (int) $count, "Dry-run must not create director_property for '$varname'");
        }
    }

    public function testLiveMigrationCreatesDirectorPropertyRows(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();
        $this->createAllFixtures($db);

        $cmd = new TestableMigrateCommand($db);
        $cmd->runDatafields();

        $dba = $db->getDbAdapter();
        foreach (self::MIGRATABLE as $varname) {
            $count = $dba->fetchOne(
                $dba->select()->from('director_property', ['cnt' => 'COUNT(*)'])->where('key_name = ?', $varname)
            );
            $this->assertEquals(1, (int) $count, "Migration must create director_property for '$varname'");
        }
    }

    public function testArrayDatafieldMigratesAsDynamicArray(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();
        $this->createAllFixtures($db);

        $cmd = new TestableMigrateCommand($db);
        $cmd->runDatafields();

        $dba = $db->getDbAdapter();
        $row = $dba->fetchRow(
            $dba->select()->from('director_property', ['value_type'])->where('key_name = ?', self::VAR_HTTP_VHOSTS)
        );

        $this->assertNotFalse($row, 'http_vhosts property must be created');
        $this->assertEquals('dynamic-array', $row->value_type);
    }

    public function testDatalistStrictMigratesCorrectly(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();
        $this->createAllFixtures($db);

        $cmd = new TestableMigrateCommand($db);
        $cmd->runDatafields();

        $dba = $db->getDbAdapter();
        $row = $dba->fetchRow(
            $dba->select()->from('director_property', ['value_type'])->where('key_name = ?', self::VAR_ENV_CHOICES)
        );

        $this->assertNotFalse($row, 'env_choices property must be created');
        $this->assertEquals('datalist-strict', $row->value_type);
    }

    public function testDatalistNonStrictMigratesCorrectly(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();
        $this->createAllFixtures($db);

        $cmd = new TestableMigrateCommand($db);
        $cmd->runDatafields();

        $dba = $db->getDbAdapter();
        $row = $dba->fetchRow(
            $dba->select()->from('director_property', ['value_type'])->where('key_name = ?', self::VAR_ENV_SUGGEST)
        );

        $this->assertNotFalse($row, 'env_suggest property must be created');
        $this->assertEquals('datalist-non-strict', $row->value_type);
    }

    public function testDatalistWithoutExplicitBehaviorDefaultsToStrict(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();
        $this->createAllFixtures($db);

        $cmd = new TestableMigrateCommand($db);
        $cmd->runDatafields();

        $dba = $db->getDbAdapter();
        $row = $dba->fetchRow(
            $dba->select()->from('director_property', ['value_type'])
                ->where('key_name = ?', self::VAR_ENV_CHOICES_DEFAULT_BEHAVIOR)
        );

        $this->assertNotFalse($row, 'env_choices_default_behavior property must be created');
        $this->assertEquals(
            'datalist-strict',
            $row->value_type,
            'a datalist datafield with no explicit "behavior" setting must migrate as strict, '
            . 'matching DataTypeDatalist\'s own default'
        );
    }

    public function testDatalistStrictMigrationLinksDatalist(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();
        $this->createAllFixtures($db);

        $cmd = new TestableMigrateCommand($db);
        $cmd->runDatafields();

        $dba = $db->getDbAdapter();
        $property = $dba->fetchRow(
            $dba->select()->from('director_property', ['uuid'])->where('key_name = ?', self::VAR_ENV_CHOICES)
        );
        $this->assertNotFalse($property, 'env_choices property must be created');

        $linkedListName = $dba->fetchOne(
            $dba->select()->from(['dd' => 'director_datalist'], ['list_name'])
                ->join(['dpdl' => 'director_property_datalist'], 'dpdl.list_uuid = dd.uuid', [])
                ->where(
                    'dpdl.property_uuid = ?',
                    DbUtil::quoteBinaryCompat(DbUtil::binaryResult($property->uuid), $dba)
                )
        );

        $this->assertEquals(
            self::LIST_NAME,
            $linkedListName,
            'migrating a legacy datalist-strict datafield must link the new property to its datalist'
        );
    }

    public function testDeleteOptionRemovesMigratedDatafields(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();
        $this->createAllFixtures($db);

        $cmd = new TestableMigrateCommand($db, ['--delete']);
        $cmd->runDatafields();

        $dba = $db->getDbAdapter();
        foreach (self::MIGRATABLE as $varname) {
            $dfCount = $dba->fetchOne(
                $dba->select()->from('director_datafield', ['cnt' => 'COUNT(*)'])->where('varname = ?', $varname)
            );
            $this->assertEquals(0, (int) $dfCount, "--delete must remove director_datafield for '$varname'");

            $propCount = $dba->fetchOne(
                $dba->select()->from('director_property', ['cnt' => 'COUNT(*)'])->where('key_name = ?', $varname)
            );
            $this->assertEquals(1, (int) $propCount, "director_property must survive --delete for '$varname'");
        }
    }

    public function testDeleteIsSkippedOnDryRun(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();
        $this->createAllFixtures($db);

        $cmd = new TestableMigrateCommand($db, ['--dry-run', '--delete']);
        $cmd->runDatafields();

        $dba = $db->getDbAdapter();
        foreach (self::MIGRATABLE as $varname) {
            $count = $dba->fetchOne(
                $dba->select()->from('director_datafield', ['cnt' => 'COUNT(*)'])->where('varname = ?', $varname)
            );

            $this->assertEquals(
                1,
                (int) $count,
                "--dry-run --delete must not remove director_datafield for '$varname'"
            );
        }
    }

    public function testCategorizedDatafieldIsSkipped(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();
        $this->createAllFixtures($db);

        $cmd = new TestableMigrateCommand($db);
        $cmd->runDatafields();

        $dba = $db->getDbAdapter();
        $count = $dba->fetchOne(
            $dba->select()->from(
                'director_property',
                ['cnt' => 'COUNT(*)']
            )->where('key_name = ?', self::VAR_CATEGORIZED)
        );
        $this->assertEquals(0, (int) $count, 'Categorized datafield must not be migrated');
    }

    public function testHiddenStringFieldMigratesAsSensitive(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();
        $this->createAllFixtures($db);

        $cmd = new TestableMigrateCommand($db);
        $cmd->runDatafields();

        $dba = $db->getDbAdapter();
        $row = $dba->fetchRow(
            $dba->select()->from('director_property', ['value_type'])->where('key_name = ?', self::VAR_HIDDEN)
        );

        $this->assertNotFalse($row, 'snmp_community property must be created');
        $this->assertEquals(
            'sensitive',
            $row->value_type,
            'a legacy string datafield with visibility=hidden must migrate as the sensitive value type'
        );
    }

    public function testUnsupportedTypeIsSkipped(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();
        $this->createAllFixtures($db);

        $cmd = new TestableMigrateCommand($db);
        $cmd->runDatafields();

        $dba = $db->getDbAdapter();
        $count = $dba->fetchOne(
            $dba->select()->from('director_property', ['cnt' => 'COUNT(*)'])->where('key_name = ?', self::VAR_SQL_QUERY)
        );
        $this->assertEquals(0, (int) $count, 'SqlQuery datafield must not be migrated (unsupported type)');
    }

    public function testUnsupportedTimeTypeIsSkippedEvenWithoutVerbose(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();
        $this->createAllFixtures($db);

        $cmd = new TestableMigrateCommand($db);
        $cmd->runDatafields();

        $dba = $db->getDbAdapter();
        $count = $dba->fetchOne(
            $dba->select()
                ->from('director_property', ['cnt' => 'COUNT(*)'])
                ->where('key_name = ?', self::VAR_TIME_FIELD)
        );
        $this->assertEquals(0, (int) $count);
    }

    public function testTotalMigratedCountExcludesUnsupportedTypes(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();
        $this->createAllFixtures($db);

        $cmd = new TestableMigrateCommand($db);
        $output = $cmd->runDatafields();

        $expectedMigrated = count(self::MIGRATABLE);
        $this->assertStringContainsString(
            "Total number of datafields migrated: $expectedMigrated\n",
            $output,
            'the migrated count must not include datafields with an unsupported type that were skipped'
        );
    }

    public function testSkippedCountStaysCorrectAfterDelete(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();
        $this->createAllFixtures($db);

        $totalBefore = count(DirectorDatafield::loadAll($db));
        $expectedMigrated = count(self::MIGRATABLE);
        $expectedSkipped = $totalBefore - $expectedMigrated;

        $cmd = new TestableMigrateCommand($db, ['--delete']);
        $output = $cmd->runDatafields();

        $this->assertStringContainsString(
            "Total number of datafields skipped: $expectedSkipped\n",
            $output,
            '--delete must not shrink the skipped count by counting datafields after they were removed'
        );
    }

    public function testMigrateDatafieldsRollsBackOnMidLoopFailure(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();
        $dba = $db->getDbAdapter();

        $sharedUuid = Uuid::uuid4()->getBytes();
        $customProperties = [
            self::PREFIX . 'tls_cert_path' => [
                'datafield_id' => 90001,
                'uuid'         => $sharedUuid,
                'key_name'     => self::PREFIX . 'tls_cert_path',
                'label'        => null,
                'description'  => null,
                'category_id'  => null,
                'value_type'   => 'string',
            ],
            self::PREFIX . 'tls_key_path' => [
                'datafield_id' => 90002,
                'uuid'         => $sharedUuid,
                'key_name'     => self::PREFIX . 'tls_key_path',
                'label'        => null,
                'description'  => null,
                'category_id'  => null,
                'value_type'   => 'string',
            ],
        ];

        $cmd = new TestableMigrateCommand($db);

        try {
            self::callMethod($cmd, 'migrateDatafields', [$customProperties, false]);
            $this->fail('Expected an exception from the duplicate uuid on the second insert');
        } catch (\Throwable $e) {
            // expected
        }

        $this->assertFalse(
            $dba->getConnection()->inTransaction(),
            'migrateDatafields() must roll back its transaction when interrupted by an exception'
        );

        $count = $dba->fetchOne(
            $dba->select()->from('director_property', ['cnt' => 'COUNT(*)'])
                ->where('key_name = ?', self::PREFIX . 'tls_cert_path')
        );
        $this->assertEquals(
            0,
            (int) $count,
            'the first insert must be rolled back along with the failing second one'
        );
    }

    public function testDuplicateNamesAreSkipped(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();
        $this->createAllFixtures($db);

        $cmd = new TestableMigrateCommand($db);
        $cmd->runDatafields();

        $dba = $db->getDbAdapter();
        $count = $dba->fetchOne(
            $dba->select()->from('director_property', ['cnt' => 'COUNT(*)'])->where('key_name = ?', self::VAR_DUP)
        );
        $this->assertEquals(0, (int) $count, 'Duplicate-named datafield must not be migrated');
    }

    public function testCaseOnlyDuplicateNamesAreBothSkipped(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();
        $this->createAllFixtures($db);

        // key_name is case-insensitive in the new schema, so "region" and "Region"
        // collide there even though the legacy datafield table happily holds both.
        DirectorDatafield::create([
            'varname'  => self::VAR_CASE_DUP_LOWER,
            'caption'  => 'Region',
            'datatype' => 'Icinga\Module\Director\DataType\DataTypeString',
        ], $db)->store();

        DirectorDatafield::create([
            'varname'  => self::VAR_CASE_DUP_UPPER,
            'caption'  => 'Region (added later by someone else)',
            'datatype' => 'Icinga\Module\Director\DataType\DataTypeString',
        ], $db)->store();

        $cmd = new TestableMigrateCommand($db, ['--verbose']);
        $output = $cmd->runDatafields();

        $this->assertStringContainsString(self::VAR_CASE_DUP_LOWER, $output);
        $this->assertStringContainsString(self::VAR_CASE_DUP_UPPER, $output);

        $dba = $db->getDbAdapter();
        foreach ([self::VAR_CASE_DUP_LOWER, self::VAR_CASE_DUP_UPPER] as $varname) {
            $count = $dba->fetchOne(
                $dba->select()->from('director_property', ['cnt' => 'COUNT(*)'])->where('key_name = ?', $varname)
            );
            $this->assertEquals(0, (int) $count, "case-only duplicate '$varname' must not be migrated");
        }
    }

    public function testExistingCustomPropertyBlocksMigrationRegardlessOfCase(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();
        $this->createAllFixtures($db);

        DirectorDatafield::create([
            'varname'  => self::VAR_CASE_EXISTING,
            'caption'  => 'Datacenter',
            'datatype' => 'Icinga\Module\Director\DataType\DataTypeString',
        ], $db)->store();

        // Pre-create a director_property whose key_name only differs in case
        $existingKeyName = strtolower(self::VAR_CASE_EXISTING);
        $db->insert('director_property', [
            'uuid'       => DbUtil::quoteBinaryCompat(Uuid::uuid4()->getBytes(), $db->getDbAdapter()),
            'key_name'   => $existingKeyName,
            'value_type' => 'string',
        ]);

        $cmd = new TestableMigrateCommand($db);
        $cmd->runDatafields();

        $dba = $db->getDbAdapter();
        $count = $dba->fetchOne(
            $dba->select()->from('director_property', ['cnt' => 'COUNT(*)'])->where('key_name = ?', $existingKeyName)
        );
        $this->assertEquals(
            1,
            (int) $count,
            'a legacy datafield differing only in case from an existing property must not be migrated'
        );
    }

    public function testExistingCustomPropertyBlocksMigration(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();
        $this->createAllFixtures($db);

        // Pre-create a director_property with key_name matching VAR_ENV
        $db->insert('director_property', [
            'uuid'       => DbUtil::quoteBinaryCompat(Uuid::uuid4()->getBytes(), $db->getDbAdapter()),
            'key_name'   => self::VAR_ENV,
            'value_type' => 'string',
        ]);

        $cmd = new TestableMigrateCommand($db);
        $cmd->runDatafields();

        $dba = $db->getDbAdapter();
        $count = $dba->fetchOne(
            $dba->select()->from('director_property', ['cnt' => 'COUNT(*)'])->where('key_name = ?', self::VAR_ENV)
        );
        $this->assertEquals(1, (int) $count, 'Pre-existing custom property must not be duplicated by migration');
    }

    public function testObjectTemplateBindingPreservesIsRequired(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();
        $this->createAllFixtures($db);
        $this->createHostFieldBinding($db);

        $cmd = new TestableMigrateCommand($db);
        $cmd->runDatafields();

        $dba = $db->getDbAdapter();
        $row = $dba->fetchRow(
            $dba->select()->from(['ihp' => 'icinga_host_property'], ['required'])
                ->join(['dp' => 'director_property'], 'dp.uuid = ihp.property_uuid', [])
                ->where('dp.key_name = ?', self::VAR_ENV)
        );

        $this->assertNotFalse($row, 'icinga_host_property row must be created for the bound host');
        $this->assertEquals('y', $row->required, 'is_required must be carried over into the new required column');
    }

    public function testObjectTemplateBindingWarnsAboutUnmigratedVarFilter(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();
        $this->createAllFixtures($db);
        $this->createHostFieldBinding($db);

        $cmd = new TestableMigrateCommand($db);
        $output = $cmd->runDatafields();

        $this->assertStringContainsString(
            "Datafield '" . self::VAR_ENV . "' has a var_filter set for its icinga_host binding",
            $output
        );

        $dba = $db->getDbAdapter();
        $count = $dba->fetchOne(
            $dba->select()->from(['ihp' => 'icinga_host_property'], ['cnt' => 'COUNT(*)'])
                ->join(['dp' => 'director_property'], 'dp.uuid = ihp.property_uuid', [])
                ->where('dp.key_name = ?', self::VAR_ENV)
        );
        $this->assertEquals(1, (int) $count, 'the binding must still be created even though its filter is dropped');
    }

    public function testObjectTemplateBindingWithoutFilterDoesNotWarn(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();
        $this->createAllFixtures($db);
        $this->createHostFieldBinding($db);

        $cmd = new TestableMigrateCommand($db);
        $output = $cmd->runDatafields();

        $this->assertStringNotContainsString(
            "Datafield '" . self::VAR_CHECK_INTERVAL . "' has a var_filter",
            $output
        );

        $dba = $db->getDbAdapter();
        $row = $dba->fetchRow(
            $dba->select()->from(['ihp' => 'icinga_host_property'], ['required'])
                ->join(['dp' => 'director_property'], 'dp.uuid = ihp.property_uuid', [])
                ->where('dp.key_name = ?', self::VAR_CHECK_INTERVAL)
        );

        $this->assertNotFalse($row, 'icinga_host_property row must be created for the bound host');
        $this->assertEquals('n', $row->required);
    }

    protected function tearDown(): void
    {
        if ($this->hasDb()) {
            $db = $this->getDb();
            $dba = $db->getDbAdapter();
            if ($dba->getConnection()->inTransaction()) {
                $dba->getConnection()->rollBack();
            }

            // Delete the host before the properties/datafields below — icinga_host cascades
            // to icinga_host_field and icinga_host_property.
            $dba->delete('icinga_host', ['object_name = ?' => self::HOST_NAME]);

            $this->deleteTestProperties($db);
            $this->deleteTestDatafields($db);
            $this->deleteTestCategory($db);
            $this->deleteTestDatalist($db);
        }

        parent::tearDown();
    }

    // -------------------------------------------------------------------------
    // Fixture helpers
    // -------------------------------------------------------------------------

    private function createAllFixtures(Db $db): void
    {
        if (! DirectorDatalist::exists(self::LIST_NAME, $db)) {
            DirectorDatalist::create(['list_name' => self::LIST_NAME, 'owner' => 'test'], $db)->store();
        }
        $datalist = DirectorDatalist::load(self::LIST_NAME, $db);
        $datalistId = $datalist->get('id');

        if (! DirectorDatafieldCategory::exists(self::CAT_NAME, $db)) {
            DirectorDatafieldCategory::create(['category_name' => self::CAT_NAME], $db)->store();
        }

        $category = DirectorDatafieldCategory::load(self::CAT_NAME, $db);
        $categoryId = $category->get('id');

        $this->deleteTestDatafields($db);

        // 1. env — string
        DirectorDatafield::create([
            'varname'  => self::VAR_ENV,
            'caption'  => 'Environment',
            'datatype' => 'Icinga\Module\Director\DataType\DataTypeString',
        ], $db)->store();

        // 2. http_vhosts — array
        DirectorDatafield::create([
            'varname'  => self::VAR_HTTP_VHOSTS,
            'caption'  => 'HTTP Vhosts',
            'datatype' => 'Icinga\Module\Director\DataType\DataTypeArray',
        ], $db)->store();

        // 3. check_interval — number
        DirectorDatafield::create([
            'varname'  => self::VAR_CHECK_INTERVAL,
            'caption'  => 'Check Interval',
            'datatype' => 'Icinga\Module\Director\DataType\DataTypeNumber',
        ], $db)->store();

        // 4. env_choices — datalist-strict
        $field = DirectorDatafield::create([
            'varname'  => self::VAR_ENV_CHOICES,
            'caption'  => 'Environment Choices',
            'datatype' => 'Icinga\Module\Director\DataType\DataTypeDatalist',
        ], $db);
        $field->set('behavior', 'strict');
        $field->set('data_type', 'string');
        $field->set('datalist_id', $datalistId);
        $field->store();

        // 5. env_suggest — datalist-non-strict
        $field = DirectorDatafield::create([
            'varname'  => self::VAR_ENV_SUGGEST,
            'caption'  => 'Environment Suggest',
            'datatype' => 'Icinga\Module\Director\DataType\DataTypeDatalist',
        ], $db);
        $field->set('behavior', 'suggest');
        $field->set('data_type', 'string');
        $field->set('datalist_id', $datalistId);
        $field->store();

        // 5b. env_choices_default_behavior — datalist datafield with no explicit 'behavior'
        // setting; must default to strict, matching DataTypeDatalist::getSetting('behavior', 'strict').
        $field = DirectorDatafield::create([
            'varname'  => self::VAR_ENV_CHOICES_DEFAULT_BEHAVIOR,
            'caption'  => 'Environment Choices Default Behavior',
            'datatype' => 'Icinga\Module\Director\DataType\DataTypeDatalist',
        ], $db);
        $field->set('data_type', 'string');
        $field->set('datalist_id', $datalistId);
        $field->store();

        // 6. sql_query_field — unsupported type
        DirectorDatafield::create([
            'varname'  => self::VAR_SQL_QUERY,
            'caption'  => 'SQL Query',
            'datatype' => 'Icinga\Module\Director\DataType\DataTypeSqlQuery',
        ], $db)->store();

        // 7. categorized_field — has a category (skip)
        $field = DirectorDatafield::create([
            'varname'     => self::VAR_CATEGORIZED,
            'caption'     => 'Categorized Field',
            'datatype'    => 'Icinga\Module\Director\DataType\DataTypeString',
            'category_id' => $categoryId,
        ], $db);
        $field->store();

        // 8. snmp_community — string with visibility=hidden, migrates as 'sensitive'
        $field = DirectorDatafield::create([
            'varname'  => self::VAR_HIDDEN,
            'caption'  => 'SNMP Community String',
            'datatype' => 'Icinga\Module\Director\DataType\DataTypeString',
        ], $db);
        $field->set('visibility', 'hidden');
        $field->store();

        // 9. notification_email × 2 — duplicate varname (skip both)
        //    DirectorDatafield has no uniqueness constraint on varname, so raw insert is safe.
        $dba = $db->getDbAdapter();
        $dba->insert('director_datafield', [
            'uuid'     => DbUtil::quoteBinaryCompat(Uuid::uuid4()->getBytes(), $dba),
            'varname'  => self::VAR_DUP,
            'caption'  => 'Notification Email (added by the ops team)',
            'datatype' => 'Icinga\Module\Director\DataType\DataTypeString',
        ]);
        $dba->insert('director_datafield', [
            'uuid'     => DbUtil::quoteBinaryCompat(Uuid::uuid4()->getBytes(), $dba),
            'varname'  => self::VAR_DUP,
            'caption'  => 'Notification Email (added by the NOC)',
            'datatype' => 'Icinga\Module\Director\DataType\DataTypeString',
        ]);

        // 10. time_field — unsupported type
        DirectorDatafield::create([
            'varname'  => self::VAR_TIME_FIELD,
            'caption'  => 'Time Field',
            'datatype' => 'Icinga\Module\Director\DataType\DataTypeTime',
        ], $db)->store();
    }

    private function createHostFieldBinding(Db $db): IcingaHost
    {
        $host = IcingaHost::create([
            'object_name' => self::HOST_NAME,
            'object_type' => 'template',
        ], $db);
        $host->store();

        $dba = $db->getDbAdapter();
        $envFieldId = $dba->fetchOne(
            $dba->select()->from('director_datafield', ['id'])->where('varname = ?', self::VAR_ENV)
        );
        IcingaHostField::create([
            'host_id'      => $host->get('id'),
            'datafield_id' => $envFieldId,
            'is_required'  => 'y',
            'var_filter'   => 'host.vars.os=Linux',
        ], $db)->store();

        $checkIntervalFieldId = $dba->fetchOne(
            $dba->select()->from('director_datafield', ['id'])->where('varname = ?', self::VAR_CHECK_INTERVAL)
        );
        IcingaHostField::create([
            'host_id'      => $host->get('id'),
            'datafield_id' => $checkIntervalFieldId,
            'is_required'  => 'n',
        ], $db)->store();

        return $host;
    }

    private function deleteTestDatafields(Db $db): void
    {
        $dba = $db->getDbAdapter();
        foreach (self::ALL_TEST_VARS as $varname) {
            $rows = $dba->fetchAll(
                $dba->select()->from('director_datafield', ['id'])->where('varname = ?', $varname)
            );
            foreach ($rows as $row) {
                $dba->delete('director_datafield_setting', $dba->quoteInto('datafield_id = ?', $row->id));
            }

            $dba->delete('director_datafield', $dba->quoteInto('varname = ?', $varname));
        }
    }

    private function deleteTestProperties(Db $db): void
    {
        $dba = $db->getDbAdapter();
        foreach (self::ALL_TEST_VARS as $varname) {
            $rows = $dba->fetchAll(
                $dba->select()->from('director_property', ['uuid'])->where('key_name = ?', $varname)
            );

            foreach ($rows as $row) {
                $dba->delete(
                    'director_property',
                    $dba->quoteInto(
                        'parent_uuid = ?',
                        DbUtil::quoteBinaryCompat(DbUtil::binaryResult($row->uuid), $dba)
                    )
                );
            }

            $dba->delete('director_property', $dba->quoteInto('key_name = ?', $varname));
        }
    }

    private function deleteTestCategory(Db $db): void
    {
        if (DirectorDatafieldCategory::exists(self::CAT_NAME, $db)) {
            $db->getDbAdapter()->delete(
                'director_datafield_category',
                $db->getDbAdapter()->quoteInto('category_name = ?', self::CAT_NAME)
            );
        }
    }

    private function deleteTestDatalist(Db $db): void
    {
        if (DirectorDatalist::exists(self::LIST_NAME, $db)) {
            DirectorDatalist::load(self::LIST_NAME, $db)->delete();
        }
    }
}
