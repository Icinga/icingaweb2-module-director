<?php

// SPDX-FileCopyrightText: 2026 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Tests\Icinga\Module\Director\DirectorObject\Automation;

use Icinga\Module\Director\Db\DbUtil;
use Icinga\Module\Director\DirectorObject\Automation\Basket;
use Icinga\Module\Director\DirectorObject\Automation\BasketDiff;
use Icinga\Module\Director\DirectorObject\Automation\BasketSnapshot;
use Icinga\Module\Director\Objects\DirectorProperty;
use Icinga\Module\Director\Test\BaseTestCase;
use Ramsey\Uuid\Uuid;

class BasketDiffTest extends BaseTestCase
{
    private const PREFIX = '___TEST___';

    public function testDiffReportsUnchangedWhenNothingChanged(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();

        $rootUuid = Uuid::uuid4();
        DirectorProperty::create([
            'uuid'       => $rootUuid->getBytes(),
            'key_name'   => self::PREFIX . 'snmp_settings',
            'value_type' => 'fixed-dictionary',
            'label'      => 'SNMP Settings',
        ], $db)->store();

        DirectorProperty::create([
            'uuid'        => Uuid::uuid4()->getBytes(),
            'key_name'    => 'community',
            'parent_uuid' => $rootUuid->getBytes(),
            'value_type'  => 'string',
        ], $db)->store();

        $propertyUuidString = $rootUuid->toString();
        $exportedProperty = DirectorProperty::loadWithUniqueId($rootUuid, $db)->export();
        $basket = Basket::create(['uuid' => Uuid::uuid4()->getBytes(), 'basket_name' => self::PREFIX . 'basket2']);
        $snapshot = BasketSnapshot::forBasketFromJson(
            $basket,
            json_encode(['CustomVariable' => [$propertyUuidString => $exportedProperty]])
        );

        $diff = new BasketDiff($snapshot, $db);

        $this->assertFalse(
            $diff->hasChangedFor('CustomVariable', $propertyUuidString, $rootUuid),
            'the diff must not report a change when nothing was actually modified'
        );
    }

    protected function tearDown(): void
    {
        if ($this->hasDb()) {
            $db = $this->getDb();
            $dba = $db->getDbAdapter();

            foreach ([self::PREFIX . 'snmp_settings'] as $keyName) {
                $rows = $dba->fetchAll(
                    $dba->select()->from('director_property', ['uuid'])->where('key_name = ?', $keyName)
                );
                foreach ($rows as $row) {
                    $this->deletePropertyTree($dba, DbUtil::binaryResult($row->uuid));
                }
            }
        }

        parent::tearDown();
    }

    /**
     * Delete a director_property row along with all of its descendants, however deep the
     * nesting goes.
     *
     * @return void
     */
    private function deletePropertyTree($dba, string $uuid): void
    {
        $childUuids = array_map(
            [DbUtil::class, 'binaryResult'],
            $dba->fetchCol(
                $dba->select()->from('director_property', ['uuid'])->where(
                    'parent_uuid = ?',
                    DbUtil::quoteBinaryCompat($uuid, $dba)
                )
            )
        );
        foreach ($childUuids as $childUuid) {
            $this->deletePropertyTree($dba, $childUuid);
        }
        $dba->delete(
            'director_property_datalist',
            $dba->quoteInto('property_uuid = ?', DbUtil::quoteBinaryCompat($uuid, $dba))
        );
        $dba->delete(
            'director_property',
            $dba->quoteInto('uuid = ?', DbUtil::quoteBinaryCompat($uuid, $dba))
        );
    }
}
