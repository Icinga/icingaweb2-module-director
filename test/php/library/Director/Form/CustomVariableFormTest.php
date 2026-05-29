<?php

// SPDX-FileCopyrightText: 2026 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Tests\Icinga\Module\Director\Form;

use Icinga\Module\Director\Db\DbUtil;
use Icinga\Module\Director\Test\BaseTestCase;
use Tests\Icinga\Module\Director\Form\Lib\TestableCustomVariableForm;

class CustomVariableFormTest extends BaseTestCase
{
    /** @var string[] Key names created during tests, for tearDown cleanup */
    private array $createdKeyNames = [];

    public function testAddStringPropertyCreatesRow(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();
        $form = new TestableCustomVariableForm($db);
        $form->setTestValues([
            'key_name'    => '___TEST___environment',
            'value_type'  => 'string',
            'label'       => 'Environment Tag',
            'description' => 'Deployment environment: production, staging, or dev',
        ]);
        $this->createdKeyNames[] = '___TEST___environment';

        self::callMethod($form, 'onSuccess', []);

        $dba = $db->getDbAdapter();
        $row = $dba->fetchRow(
            $dba->select()
                ->from('director_property', ['key_name', 'value_type'])
                ->where('key_name = ?', '___TEST___environment')
        );

        $this->assertNotFalse($row, 'director_property row should be created');
        $this->assertSame('string', $row->value_type);
        $this->assertNotNull($form->getUUid(), 'form UUID should be set after creation');
    }

    public function testAddDynamicArrayPropertyCreatesParentAndChildRows(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();
        $form = new TestableCustomVariableForm($db);
        $form->setTestValues([
            'key_name'    => '___TEST___contact_groups',
            'value_type'  => 'dynamic-array',
            'item_type'   => 'string',
            'label'       => 'Contact Groups',
            'description' => 'Teams that receive alerts for this host (e.g. noc, linux-ops)',
        ]);
        $this->createdKeyNames[] = '___TEST___contact_groups';

        self::callMethod($form, 'onSuccess', []);

        $dba = $db->getDbAdapter();
        $parentRow = $dba->fetchRow(
            $dba->select()
                ->from('director_property', ['value_type'])
                ->where('key_name = ?', '___TEST___contact_groups')
        );
        $this->assertNotFalse($parentRow, 'parent director_property row should be created');
        $this->assertSame('dynamic-array', $parentRow->value_type);

        $parentUuid = $form->getUUid();
        $this->assertNotNull($parentUuid, 'form UUID should be set after creation');
        $childRows = $dba->fetchAll(
            $dba->select()
                ->from('director_property', ['key_name', 'value_type'])
                ->where('parent_uuid = ?', DbUtil::quoteBinaryCompat($parentUuid->getBytes(), $dba))
        );
        $this->assertCount(1, $childRows, 'exactly one child row should be created for the item type');
        $this->assertSame('0', (string) $childRows[0]->key_name);
        $this->assertSame('string', $childRows[0]->value_type);
    }

    public function testUpdateStringPropertyKeyName(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();

        $createForm = new TestableCustomVariableForm($db);
        $createForm->setTestValues([
            'key_name'    => '___TEST___http_uri',
            'value_type'  => 'string',
            'label'       => 'HTTP URI',
            'description' => 'URI path to probe, e.g. /api/health',
        ]);
        $this->createdKeyNames[] = '___TEST___http_uri';
        $this->createdKeyNames[] = '___TEST___http_url';
        self::callMethod($createForm, 'onSuccess', []);
        $uuid = $createForm->getUUid();

        $updateForm = new TestableCustomVariableForm($db, $uuid);
        $updateForm->setTestValues([
            'key_name'    => '___TEST___http_url',
            'value_type'  => 'string',
            'label'       => 'HTTP URL',
            'description' => 'URI path to probe, e.g. /api/health',
        ]);
        self::callMethod($updateForm, 'onSuccess', []);

        $dba = $db->getDbAdapter();
        $renamedRow = $dba->fetchRow(
            $dba->select()
                ->from('director_property', ['key_name'])
                ->where('key_name = ?', '___TEST___http_url')
        );
        $this->assertNotFalse($renamedRow, 'renamed director_property row should exist');

        $oldRow = $dba->fetchRow(
            $dba->select()
                ->from('director_property', ['key_name'])
                ->where('key_name = ?', '___TEST___http_uri')
        );
        $this->assertFalse($oldRow, 'original key_name should not exist after rename');
    }

    public function tearDown(): void
    {
        if ($this->hasDb()) {
            $dba = $this->getDb()->getDbAdapter();
            foreach ($this->createdKeyNames as $keyName) {
                $rows = $dba->fetchAll(
                    $dba->select()
                        ->from('director_property', ['uuid'])
                        ->where('key_name = ?', $keyName)
                );
                foreach ($rows as $row) {
                    $dba->delete(
                        'director_property',
                        $dba->quoteInto(
                            'parent_uuid = ?',
                            DbUtil::quoteBinaryCompat(DbUtil::binaryResult($row->uuid), $dba)
                        )
                    );
                    $dba->delete('director_property', $dba->quoteInto('key_name = ?', $keyName));
                }
            }
        }

        parent::tearDown();
    }
}
