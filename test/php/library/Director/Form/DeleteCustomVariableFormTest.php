<?php

// SPDX-FileCopyrightText: 2026 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Tests\Icinga\Module\Director\Form;

use Icinga\Module\Director\Db\DbUtil;
use Icinga\Module\Director\Forms\DeleteCustomVariableForm;
use Icinga\Module\Director\Objects\DirectorDatalist;
use Icinga\Module\Director\Objects\DirectorProperty;
use Icinga\Module\Director\Test\BaseTestCase;
use Ramsey\Uuid\Uuid;

class DeleteCustomVariableFormTest extends BaseTestCase
{
    public function testDeleteStringPropertyRemovesRow(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();
        $property = DirectorProperty::create([
            'uuid'       => Uuid::uuid4()->getBytes(),
            'key_name'   => '___TEST___environment',
            'value_type' => 'string',
            'label'      => 'Environment Tag',
        ], $db);
        $property->store();

        $form = new DeleteCustomVariableForm($db, [
            'uuid'        => $property->get('uuid'),
            'key_name'    => '___TEST___environment',
            'value_type'  => 'string',
            'label'       => 'Environment Tag',
            'description' => null,
            'parent_uuid' => null,
        ]);

        self::callMethod($form, 'onSuccess', []);

        $dba = $db->getDbAdapter();
        $row = $dba->fetchRow(
            $dba->select()
                ->from('director_property', ['uuid'])
                ->where('key_name = ?', '___TEST___environment')
        );
        $this->assertFalse($row, 'director_property row should be deleted');
    }

    public function testDeletePropertyWithChildrenRemovesBothRows(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();
        $parentUuid = Uuid::uuid4();

        $parent = DirectorProperty::create([
            'uuid'       => $parentUuid->getBytes(),
            'key_name'   => '___TEST___snmp_v3',
            'value_type' => 'fixed-dictionary',
            'label'      => 'SNMPv3 Credentials',
        ], $db);
        $parent->store();

        $child = DirectorProperty::create([
            'uuid'        => Uuid::uuid4()->getBytes(),
            'parent_uuid' => $parentUuid->getBytes(),
            'key_name'    => 'auth_protocol',
            'value_type'  => 'string',
            'label'       => 'Auth Protocol',
        ], $db);
        $child->store();

        $form = new DeleteCustomVariableForm($db, [
            'uuid'        => $parent->get('uuid'),
            'key_name'    => '___TEST___snmp_v3',
            'value_type'  => 'fixed-dictionary',
            'label'       => 'SNMPv3 Credentials',
            'description' => null,
            'parent_uuid' => null,
        ]);

        self::callMethod($form, 'onSuccess', []);

        $dba = $db->getDbAdapter();
        $parentRow = $dba->fetchRow(
            $dba->select()
                ->from('director_property', ['uuid'])
                ->where('key_name = ?', '___TEST___snmp_v3')
        );
        $this->assertFalse($parentRow, 'parent director_property row should be deleted');

        $childRow = $dba->fetchRow(
            $dba->select()
                ->from('director_property', ['uuid'])
                ->where('parent_uuid = ?', DbUtil::quoteBinaryCompat($parentUuid->getBytes(), $dba))
        );
        $this->assertFalse($childRow, 'child director_property row should be deleted');
    }

    public function testDeleteDatalistPropertyRemovesDatalistLink(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();

        $datalist = DirectorDatalist::create([
            'list_name' => '___TEST___severity_levels',
            'owner'     => 'test',
        ], $db);
        $datalist->store();

        $property = DirectorProperty::import((object) [
            'uuid'        => Uuid::uuid4()->toString(),
            'key_name'    => '___TEST___escalation_tier',
            'value_type'  => 'datalist-strict',
            'label'       => 'Escalation Tier',
            'description' => null,
            'parent_uuid' => null,
            'category'    => null,
            'datalist'    => '___TEST___severity_levels',
            'items'       => [],
        ], $db);
        $property->store();

        $form = new DeleteCustomVariableForm($db, [
            'uuid'        => $property->get('uuid'),
            'key_name'    => '___TEST___escalation_tier',
            'value_type'  => 'datalist-strict',
            'label'       => 'Escalation Tier',
            'description' => null,
            'parent_uuid' => null,
        ]);

        self::callMethod($form, 'onSuccess', []);

        $dba = $db->getDbAdapter();
        $linkRow = $dba->fetchRow(
            $dba->select()
                ->from('director_property_datalist', ['property_uuid'])
                ->where('property_uuid = ?', DbUtil::quoteBinaryCompat($property->get('uuid'), $dba))
        );
        $this->assertFalse($linkRow, 'director_property_datalist link should be deleted');

        $propRow = $dba->fetchRow(
            $dba->select()
                ->from('director_property', ['uuid'])
                ->where('key_name = ?', '___TEST___escalation_tier')
        );
        $this->assertFalse($propRow, 'director_property row should be deleted');
    }

    public function tearDown(): void
    {
        if ($this->hasDb()) {
            $dba = $this->getDb()->getDbAdapter();
            foreach (['___TEST___environment', '___TEST___snmp_v3', '___TEST___escalation_tier'] as $keyName) {
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

            $dba->delete('director_datalist', ['list_name = ?' => '___TEST___severity_levels']);
        }

        parent::tearDown();
    }
}
