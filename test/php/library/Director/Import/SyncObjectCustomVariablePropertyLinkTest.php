<?php

// SPDX-FileCopyrightText: 2026 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Tests\Icinga\Module\Director\Import;

use Icinga\Module\Director\Db\DbUtil;
use Icinga\Module\Director\Objects\DirectorProperty;
use Icinga\Module\Director\Objects\IcingaHost;
use Icinga\Module\Director\Test\SyncTest;
use Ramsey\Uuid\Uuid;

class SyncObjectCustomVariablePropertyLinkTest extends SyncTest
{
    protected $objectType = 'host';

    protected $keyColumn = 'host';

    private const TEMPLATE_NAME = 'SYNCTEST_prop_template';

    private const PROP_KEY_NAME = 'SYNCTEST_env';

    public function testSyncLinksAVarToAPropertyAttachedOnAnImportedTemplate(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();

        $template = IcingaHost::create([
            'object_name' => self::TEMPLATE_NAME,
            'object_type' => 'template',
        ]);
        $template->store($db);

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

        $this->runImport([[
            'host' => 'SYNCTEST_synced_host',
            'env'  => 'production',
        ]]);

        $this->setUpProperty([
            'source_expression' => '${host}',
            'destination_field' => 'object_name',
            'priority'          => 10,
        ]);
        $this->setUpProperty([
            'source_expression' => self::TEMPLATE_NAME,
            'destination_field' => 'import',
            'priority'          => 11,
        ]);
        $this->setUpProperty([
            'source_expression' => '${env}',
            'destination_field' => 'vars.' . self::PROP_KEY_NAME,
            'priority'          => 12,
        ]);

        $this->sync->apply();

        $row = $dba->fetchRow(
            $dba->select()
                ->from('icinga_host_var')
                ->where('varname = ?', self::PROP_KEY_NAME)
        );

        $this->assertNotFalse($row, 'the synced var must have been stored');
        $this->assertEquals(
            $property->get('uuid'),
            DbUtil::binaryResult($row->property_uuid),
            'a var matching a property already attached on an imported template must be linked to it'
        );
    }

    public function tearDown(): void
    {
        // The generic cleanup below deletes every SYNCTEST_ host in whatever order the
        // database hands them back, so the template can get hit before the host that
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
            $dba = $this->getDb()->getDbAdapter();
            $dba->delete('director_property', $dba->quoteInto('key_name = ?', self::PROP_KEY_NAME));
        }
    }
}
