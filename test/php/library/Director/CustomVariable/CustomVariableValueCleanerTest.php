<?php

// SPDX-FileCopyrightText: 2026 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Tests\Icinga\Module\Director\CustomVariable;

use Icinga\Module\Director\CustomVariable\CustomVariableValueCleaner;
use Icinga\Module\Director\Db\DbUtil;
use Icinga\Module\Director\Objects\DirectorProperty;
use Icinga\Module\Director\Objects\IcingaHost;
use Icinga\Module\Director\Test\BaseTestCase;
use Ramsey\Uuid\Uuid;

class CustomVariableValueCleanerTest extends BaseTestCase
{
    private const PREFIX = '___TEST___';

    private const ROOT_KEY_NAME = self::PREFIX . 'contact_ips';

    public function testKeepPropertyInPlaceNullsFixedArraySlotWithoutReindexing(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();
        $dba = $db->getDbAdapter();

        $host = IcingaHost::create([
            'object_name' => self::PREFIX . 'retype_host',
            'object_type' => 'object',
            'address'     => '192.0.2.70',
        ], $db);
        $host->store();

        $rootUuid = Uuid::uuid4();
        DirectorProperty::create([
            'uuid'       => $rootUuid->getBytes(),
            'key_name'   => self::ROOT_KEY_NAME,
            'value_type' => 'fixed-array',
            'label'      => 'Contact IPs',
        ], $db)->store();

        $itemUuids = [];
        foreach (['0', '1', '2'] as $keyName) {
            $itemUuid = Uuid::uuid4();
            $itemUuids[$keyName] = $itemUuid;
            DirectorProperty::create([
                'uuid'        => $itemUuid->getBytes(),
                'key_name'    => $keyName,
                'parent_uuid' => $rootUuid->getBytes(),
                'value_type'  => 'string',
            ], $db)->store();
        }

        $dba->insert('icinga_host_var', [
            'host_id'       => $host->get('id'),
            'varname'       => self::ROOT_KEY_NAME,
            'varvalue'      => json_encode(['10.0.0.1', '10.0.0.2', '10.0.0.3']),
            'format'        => 'json',
            'property_uuid' => DbUtil::quoteBinaryCompat($rootUuid->getBytes(), $dba),
        ]);

        $cleaner = new CustomVariableValueCleaner($db);
        $cleaner->removeObjectCustomVars(
            [
                'key_name'    => '1',
                'uuid'        => $itemUuids['1']->getBytes(),
                'parent_uuid' => $rootUuid->getBytes(),
                'value_type'  => 'string',
                'label'       => null,
                'description' => null,
            ],
            [
                'key_name'    => self::ROOT_KEY_NAME,
                'uuid'        => $rootUuid->getBytes(),
                'parent_uuid' => null,
                'value_type'  => 'fixed-array',
                'label'       => 'Contact IPs',
                'description' => null,
            ],
            true
        );

        $updatedValue = $dba->fetchOne(
            $dba->select()->from('icinga_host_var', ['varvalue'])
                ->where('host_id = ?', $host->get('id'))
                ->where('varname = ?', self::ROOT_KEY_NAME)
        );

        $this->assertEquals(
            '["10.0.0.1",null,"10.0.0.3"]',
            $updatedValue,
            'a retyped-in-place item must be nulled out at its own index, not unset and reindexed'
        );

        $siblingKeyNames = $dba->fetchCol(
            $dba->select()->from('director_property', ['key_name'])
                ->where('parent_uuid = ?', DbUtil::quoteBinaryCompat($rootUuid->getBytes(), $dba))
                ->order('key_name')
        );

        $this->assertEquals(
            ['0', '1', '2'],
            $siblingKeyNames,
            'siblings must keep their original key_name, retyping in place must not trigger a reindex'
        );
    }

    protected function tearDown(): void
    {
        if ($this->hasDb()) {
            $db = $this->getDb();
            $dba = $db->getDbAdapter();

            $dba->delete('icinga_host', ['object_name = ?' => self::PREFIX . 'retype_host']);

            $rows = $dba->fetchAll(
                $dba->select()->from('director_property', ['uuid'])->where('key_name = ?', self::ROOT_KEY_NAME)
            );
            foreach ($rows as $row) {
                $rootUuid = DbUtil::binaryResult($row->uuid);
                $dba->delete(
                    'director_property',
                    $dba->quoteInto('parent_uuid = ?', DbUtil::quoteBinaryCompat($rootUuid, $dba))
                );
            }
            $dba->delete('director_property', $dba->quoteInto('key_name = ?', self::ROOT_KEY_NAME));
        }

        parent::tearDown();
    }
}
