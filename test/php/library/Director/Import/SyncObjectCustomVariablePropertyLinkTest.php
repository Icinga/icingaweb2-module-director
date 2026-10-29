<?php

// SPDX-FileCopyrightText: 2026 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Tests\Icinga\Module\Director\Import;

use Icinga\Module\Director\Db\DbUtil;
use Icinga\Module\Director\Import\Sync;
use Icinga\Module\Director\Objects\DirectorProperty;
use Icinga\Module\Director\Objects\IcingaCommand;
use Icinga\Module\Director\Objects\IcingaHost;
use Icinga\Module\Director\Objects\SyncProperty;
use Icinga\Module\Director\Objects\SyncRule;
use Icinga\Module\Director\Test\SyncTest;
use Ramsey\Uuid\Uuid;

class SyncObjectCustomVariablePropertyLinkTest extends SyncTest
{
    protected $objectType = 'host';

    protected $keyColumn = 'host';

    private const TEMPLATE_NAME = 'SYNCTEST_prop_template';

    private const OTHER_TEMPLATE_NAME = 'SYNCTEST_other_template';

    private const PARENT_TEMPLATE_NAME = 'SYNCTEST_parent_template';

    private const PROP_KEY_NAME = 'SYNCTEST_env';

    private const COMMAND_NAME = 'SYNCTEST_ping4';

    private const OTHER_COMMAND_NAME = 'SYNCTEST_dummy';

    public function testSyncLinksAVarToAPropertyAttachedOnAnImportedTemplate(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $template = $this->createTemplate(self::TEMPLATE_NAME);
        $property = $this->createProperty($template);

        $this->runImport([[
            'host' => 'SYNCTEST_synced_host',
            'env'  => 'production',
        ]]);
        $this->setUpHostSyncProperties(self::TEMPLATE_NAME);
        $this->sync->apply();

        $this->assertVarUuid('production', $property->get('uuid'));
    }

    public function testSyncClearsUuidWhenTheProvidingTemplateIsNoLongerImported(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $template = $this->createTemplate(self::TEMPLATE_NAME);
        $this->createProperty($template);
        $this->createTemplate(self::OTHER_TEMPLATE_NAME);

        $this->runImport([[
            'host' => 'SYNCTEST_synced_host',
            'env'  => 'production',
        ]]);
        $importProperty = $this->setUpHostSyncProperties(self::TEMPLATE_NAME);
        $this->sync->apply();
        $this->assertVarUuidIsNotNull();

        // the host moves to a template that never had this property attached
        $importProperty->set('source_expression', self::OTHER_TEMPLATE_NAME)->store();
        $this->resync();
        $this->sync->apply();

        $this->assertVarUuid('production', null);
    }

    public function testSyncKeepsUuidWhenThePropertyMovesToTheNewlyImportedTemplate(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();
        $dba = $db->getDbAdapter();

        $template = $this->createTemplate(self::TEMPLATE_NAME);
        $property = $this->createProperty($template);
        $otherTemplate = $this->createTemplate(self::OTHER_TEMPLATE_NAME);

        $this->runImport([[
            'host' => 'SYNCTEST_synced_host',
            'env'  => 'production',
        ]]);
        $importProperty = $this->setUpHostSyncProperties(self::TEMPLATE_NAME);
        $this->sync->apply();
        $this->assertVarUuid('production', $property->get('uuid'));

        // the property moves to the template the host is about to import instead
        $dba->delete('icinga_host_property', $dba->quoteInto(
            'host_uuid = ?',
            DbUtil::quoteBinaryCompat($template->get('uuid'), $dba)
        ));
        $db->insert('icinga_host_property', [
            'property_uuid' => DbUtil::quoteBinaryCompat($property->get('uuid'), $dba),
            'host_uuid'     => DbUtil::quoteBinaryCompat($otherTemplate->get('uuid'), $dba),
        ]);
        $importProperty->set('source_expression', self::OTHER_TEMPLATE_NAME)->store();
        $this->resync();
        $this->sync->apply();

        $this->assertVarUuid('production', $property->get('uuid'));
    }

    public function testSyncKeepsUuidWhenStillReachableThroughAnotherImportedTemplate(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $parentTemplate = $this->createTemplate(self::PARENT_TEMPLATE_NAME);
        $property = $this->createProperty($parentTemplate);
        $this->createTemplate(self::TEMPLATE_NAME);
        $this->createTemplate(self::OTHER_TEMPLATE_NAME, [self::PARENT_TEMPLATE_NAME]);

        $this->runImport([[
            'host' => 'SYNCTEST_synced_host',
            'env'  => 'production',
        ]]);
        // sync a second import, pinned to a fixed name, no host var maps to it
        $this->setUpProperty([
            'source_expression' => self::TEMPLATE_NAME,
            'destination_field' => 'import',
            'priority'          => 11,
        ]);
        $importProperty = $this->setUpHostSyncProperties(self::PARENT_TEMPLATE_NAME, 12);
        $this->sync->apply();
        $this->assertVarUuid('production', $property->get('uuid'));

        // the host drops its direct import of the template providing the property, but
        // keeps a second import that still reaches it through its own inheritance
        $importProperty->set('source_expression', self::OTHER_TEMPLATE_NAME)->store();
        $this->resync();
        $this->sync->apply();

        $this->assertVarUuid('production', $property->get('uuid'));
    }

    public function testSyncLinksVarToCheckCommandProperty(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $property = $this->createCommandWithProperty(self::COMMAND_NAME);

        $this->runImport([[
            'host' => 'SYNCTEST_synced_host',
            'env'  => 'production',
        ]]);
        $this->setUpHostSyncWithCommand(self::COMMAND_NAME);
        $this->sync->apply();

        $this->assertVarUuid('production', $property->get('uuid'));
    }

    public function testSyncClearsUuidWhenCommandLosesVar(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $property = $this->createCommandWithProperty(self::COMMAND_NAME);
        $this->createCommand(self::OTHER_COMMAND_NAME);

        $this->runImport([[
            'host' => 'SYNCTEST_synced_host',
            'env'  => 'production',
        ]]);
        $commandProperty = $this->setUpHostSyncWithCommand(self::COMMAND_NAME);
        $this->sync->apply();
        $this->assertVarUuid('production', $property->get('uuid'));

        $commandProperty->set('source_expression', self::OTHER_COMMAND_NAME)->store();
        $this->resync();
        $this->sync->apply();

        $this->assertVarUuid('production', null);
    }

    public function testSyncClearsUuidWhenTemplateChangesCommand(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $property = $this->createCommandWithProperty(self::COMMAND_NAME);
        $this->createCommand(self::OTHER_COMMAND_NAME);
        $this->createTemplate(self::TEMPLATE_NAME, [], ['check_command' => self::COMMAND_NAME]);
        $this->createTemplate(self::OTHER_TEMPLATE_NAME, [], ['check_command' => self::OTHER_COMMAND_NAME]);

        $this->runImport([[
            'host' => 'SYNCTEST_synced_host',
            'env'  => 'production',
        ]]);
        $importProperty = $this->setUpHostSyncProperties(self::TEMPLATE_NAME);
        $this->sync->apply();
        $this->assertVarUuid('production', $property->get('uuid'));

        $importProperty->set('source_expression', self::OTHER_TEMPLATE_NAME)->store();
        $this->resync();
        $this->sync->apply();

        $this->assertVarUuid('production', null);
    }

    public function testSyncLooksUpACommandOnlyOnceForManyHosts(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $property = $this->createCommandWithProperty(self::COMMAND_NAME);
        $rows = [];
        foreach (range(1, 5) as $number) {
            $rows[] = ['host' => 'SYNCTEST_synced_host_' . $number, 'env' => 'production'];
        }

        $this->runImport($rows);
        $this->setUpHostSyncWithCommand(self::COMMAND_NAME);

        $profiler = $this->getDb()->getDbAdapter()->getProfiler();
        $profiler->setEnabled(true);
        $this->sync->apply();
        $profiler->setEnabled(false);

        $commandQueries = array_filter(
            $profiler->getQueryProfiles() ?: [],
            fn ($profile) => str_contains($profile->getQuery(), 'icinga_command_property')
        );

        // One for which keys any command has, one for the single command all hosts share
        $this->assertCount(2, $commandQueries);

        $dba = $this->getDb()->getDbAdapter();
        $unlinked = $dba->fetchOne(
            $dba->select()
                ->from('icinga_host_var', ['COUNT(*)'])
                ->where('varname = ?', self::PROP_KEY_NAME)
                ->where('property_uuid IS NULL')
        );
        $this->assertSame(0, (int) $unlinked, 'every host must be linked to the property of the shared command');
        $this->assertEquals(
            5,
            (int) $dba->fetchOne(
                $dba->select()
                    ->from('icinga_host_var', ['COUNT(*)'])
                    ->where('property_uuid = ?', DbUtil::quoteBinaryCompat($property->get('uuid'), $dba))
            )
        );
    }

    public function testSyncClearsUuidWhenCommandChangesTogetherWithAnotherField(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $property = $this->createCommandWithProperty(self::COMMAND_NAME);
        $this->createCommand(self::OTHER_COMMAND_NAME);

        $this->runImport([[
            'host'    => 'SYNCTEST_synced_host',
            'env'     => 'production',
            'address' => '192.0.2.10',
        ]]);
        $commandProperty = $this->setUpHostSyncWithCommand(self::COMMAND_NAME);
        $this->setUpProperty([
            'source_expression' => '${address}',
            'destination_field' => 'address',
            'priority'          => 13,
        ]);
        $this->sync->apply();
        $this->assertVarUuid('production', $property->get('uuid'));

        $this->runImport([[
            'host'    => 'SYNCTEST_synced_host',
            'env'     => 'production',
            'address' => '192.0.2.20',
        ]]);
        $commandProperty->set('source_expression', self::OTHER_COMMAND_NAME)->store();
        $this->resync();
        $this->sync->apply();

        $this->assertVarUuid('production', null);
    }

    private function resync(): void
    {
        // a sync rule caches its properties once loaded, a real second sync run
        // would start from a fresh process, so reload the rule to match that
        $this->rule = SyncRule::load($this->rule->get('rule_name'), $this->getDb());
        $this->sync = new Sync($this->rule);
    }

    private function createTemplate(string $name, array $imports = [], array $extra = []): IcingaHost
    {
        $template = IcingaHost::create(array_merge([
            'object_name' => $name,
            'object_type' => 'template',
            'imports'     => $imports,
        ], $extra));
        $template->store($this->getDb());

        return $template;
    }

    private function createProperty(IcingaHost $template): DirectorProperty
    {
        $db = $this->getDb();
        $property = DirectorProperty::create([
            'uuid'       => Uuid::uuid4()->getBytes(),
            'key_name'   => self::PROP_KEY_NAME,
            'value_type' => 'string',
            'label'      => 'Env',
        ], $db);
        $property->store();

        $dba = $db->getDbAdapter();
        $db->insert('icinga_host_property', [
            'property_uuid' => DbUtil::quoteBinaryCompat($property->get('uuid'), $dba),
            'host_uuid'     => DbUtil::quoteBinaryCompat($template->get('uuid'), $dba),
        ]);

        return $property;
    }

    private function setUpHostSyncProperties(string $importTemplateName, int $priority = 11): SyncProperty
    {
        $this->setUpProperty([
            'source_expression' => '${host}',
            'destination_field' => 'object_name',
            'priority'          => 10,
        ]);
        $this->setUpProperty([
            'source_expression' => $importTemplateName,
            'destination_field' => 'import',
            'priority'          => $priority,
        ]);
        $importProperty = $this->properties[count($this->properties) - 1];
        $this->setUpProperty([
            'source_expression' => '${env}',
            'destination_field' => 'vars.' . self::PROP_KEY_NAME,
            'priority'          => $priority + 1,
        ]);

        return $importProperty;
    }

    private function createCommand(string $name): IcingaCommand
    {
        $command = IcingaCommand::create([
            'object_name' => $name,
            'object_type' => 'external_object',
            'command'     => 'ping4',
        ]);
        $command->store($this->getDb());

        return $command;
    }

    private function createCommandWithProperty(string $commandName): DirectorProperty
    {
        $db = $this->getDb();
        $command = $this->createCommand($commandName);
        $property = DirectorProperty::create([
            'uuid'       => Uuid::uuid4()->getBytes(),
            'key_name'   => self::PROP_KEY_NAME,
            'value_type' => 'string',
            'label'      => 'Env',
        ], $db);
        $property->store();

        $dba = $db->getDbAdapter();
        $db->insert('icinga_command_property', [
            'property_uuid' => DbUtil::quoteBinaryCompat($property->get('uuid'), $dba),
            'command_uuid'  => DbUtil::quoteBinaryCompat($command->get('uuid'), $dba),
            'required'      => 'n',
        ]);

        return $property;
    }

    private function setUpHostSyncWithCommand(string $commandName): SyncProperty
    {
        $this->setUpProperty([
            'source_expression' => '${host}',
            'destination_field' => 'object_name',
            'priority'          => 10,
        ]);
        $this->setUpProperty([
            'source_expression' => $commandName,
            'destination_field' => 'check_command',
            'priority'          => 11,
        ]);
        $commandProperty = $this->properties[count($this->properties) - 1];
        $this->setUpProperty([
            'source_expression' => '${env}',
            'destination_field' => 'vars.' . self::PROP_KEY_NAME,
            'priority'          => 12,
        ]);

        return $commandProperty;
    }

    private function assertVarUuidIsNotNull(): void
    {
        $row = $this->getDb()->getDbAdapter()->fetchRow(
            $this->getDb()->getDbAdapter()->select()
                ->from('icinga_host_var')
                ->where('varname = ?', self::PROP_KEY_NAME)
        );

        $this->assertNotFalse($row, 'the synced var must have been stored');
        $this->assertNotNull($row->property_uuid, 'the synced var must have been linked to a property');
    }

    private function assertVarUuid(string $expectedValue, ?string $expectedUuid): void
    {
        $dba = $this->getDb()->getDbAdapter();
        $row = $dba->fetchRow(
            $dba->select()
                ->from('icinga_host_var')
                ->where('varname = ?', self::PROP_KEY_NAME)
        );

        $this->assertNotFalse($row, 'the synced var must have been stored');
        $this->assertEquals($expectedValue, $row->varvalue);

        if ($expectedUuid === null) {
            $this->assertNull(
                $row->property_uuid,
                'a var whose property is no longer reachable must have its uuid cleared'
            );
        } else {
            $this->assertEquals(
                $expectedUuid,
                DbUtil::binaryResult($row->property_uuid),
                'a var matching a currently reachable property must be linked to it'
            );
        }
    }

    public function tearDown(): void
    {
        // The generic cleanup below deletes every SYNCTEST_ host in whatever order the
        // database hands them back, so a template can get hit before something that
        // imports it. Drop the inheritance and property attachment rows by hand first.
        if ($this->hasDb()) {
            $dba = $this->getDb()->getDbAdapter();
            $dba->query(
                'DELETE FROM icinga_host_inheritance WHERE host_id IN'
                . " (SELECT id FROM icinga_host WHERE object_name LIKE 'SYNCTEST_%')"
                . ' OR parent_host_id IN'
                . " (SELECT id FROM icinga_host WHERE object_name LIKE 'SYNCTEST_%')"
            );
            $dba->query(
                'DELETE FROM icinga_host_property WHERE host_uuid IN'
                . " (SELECT uuid FROM icinga_host WHERE object_name LIKE 'SYNCTEST_%')"
            );
        }

        parent::tearDown();

        if ($this->hasDb()) {
            // The hosts using them are gone now, so the commands can be deleted
            foreach ([self::COMMAND_NAME, self::OTHER_COMMAND_NAME] as $name) {
                if (IcingaCommand::exists($name, $this->getDb())) {
                    IcingaCommand::load($name, $this->getDb())->delete();
                }
            }

            $dba = $this->getDb()->getDbAdapter();
            $dba->delete('director_property', $dba->quoteInto('key_name = ?', self::PROP_KEY_NAME));
        }
    }
}
