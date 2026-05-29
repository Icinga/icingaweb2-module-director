<?php

// SPDX-FileCopyrightText: 2026 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Tests\Icinga\Module\Director\Objects;

use Icinga\Module\Director\Db;
use Icinga\Module\Director\Db\DbUtil;
use Icinga\Module\Director\Objects\DirectorDatafield;
use Icinga\Module\Director\Objects\DirectorDatafieldCategory;
use Icinga\Module\Director\Objects\DirectorDatalist;
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

    // Non-migratable datafield varnames
    private const VAR_SQL_QUERY    = self::PREFIX . 'sql_query_field';

    private const VAR_CATEGORIZED  = self::PREFIX . 'categorized_field';

    private const VAR_HIDDEN       = self::PREFIX . 'hidden_field';

    private const VAR_DUP          = self::PREFIX . 'dup_field';

    private const LIST_NAME = self::PREFIX . 'migrate_list';

    private const CAT_NAME  = self::PREFIX . 'migrate_category';

    private const MIGRATABLE = [
        self::VAR_ENV,
        self::VAR_HTTP_VHOSTS,
        self::VAR_CHECK_INTERVAL,
        self::VAR_ENV_CHOICES,
        self::VAR_ENV_SUGGEST,
    ];

    private const ALL_TEST_VARS = [
        self::VAR_ENV,
        self::VAR_HTTP_VHOSTS,
        self::VAR_CHECK_INTERVAL,
        self::VAR_ENV_CHOICES,
        self::VAR_ENV_SUGGEST,
        self::VAR_SQL_QUERY,
        self::VAR_CATEGORIZED,
        self::VAR_HIDDEN,
        self::VAR_DUP,
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
    public function testProtectedStringFieldIsSkipped(): void
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
            $dba->select()->from('director_property', ['cnt' => 'COUNT(*)'])->where('key_name = ?', self::VAR_HIDDEN)
        );
        $this->assertEquals(0, (int) $count, 'Protected (hidden visibility) datafield must not be migrated');
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

    protected function tearDown(): void
    {
        if ($this->hasDb()) {
            $db = $this->getDb();
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

        // 8. hidden_field — protected string (visibility=hidden, skip)
        $field = DirectorDatafield::create([
            'varname'  => self::VAR_HIDDEN,
            'caption'  => 'Hidden Field',
            'datatype' => 'Icinga\Module\Director\DataType\DataTypeString',
        ], $db);
        $field->set('visibility', 'hidden');
        $field->store();

        // 9. dup_field × 2 — duplicate varname (skip both)
        //    DirectorDatafield has no uniqueness constraint on varname, so raw insert is safe.
        $dba = $db->getDbAdapter();
        $dba->insert('director_datafield', [
            'uuid'     => DbUtil::quoteBinaryCompat(Uuid::uuid4()->getBytes(), $dba),
            'varname'  => self::VAR_DUP,
            'caption'  => 'Dup A',
            'datatype' => 'Icinga\Module\Director\DataType\DataTypeString',
        ]);
        $dba->insert('director_datafield', [
            'uuid'     => DbUtil::quoteBinaryCompat(Uuid::uuid4()->getBytes(), $dba),
            'varname'  => self::VAR_DUP,
            'caption'  => 'Dup B',
            'datatype' => 'Icinga\Module\Director\DataType\DataTypeString',
        ]);
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
