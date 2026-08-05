<?php

// SPDX-FileCopyrightText: 2026 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Tests\Icinga\Module\Director\DirectorObject\Automation;

use Icinga\Module\Director\Db\DbUtil;
use Icinga\Module\Director\DirectorObject\Automation\Basket;
use Icinga\Module\Director\DirectorObject\Automation\BasketDiff;
use Icinga\Module\Director\DirectorObject\Automation\BasketSnapshot;
use Icinga\Module\Director\Objects\DirectorProperty;
use Icinga\Module\Director\Objects\IcingaHost;
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

    /**
     * Old baskets never had customVariables/fields keys. A template with none of
     * its own must still diff as unchanged against one of these.
     */
    public function testDiffReportsUnchangedWhenBasketOmitsEmptyFieldsAndCustomVariables(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();
        $templateName = self::PREFIX . 'plain-template';
        $host = IcingaHost::create([
            'object_name' => $templateName,
            'object_type' => 'template',
        ]);
        $host->store($db);

        try {
            $basket = Basket::create(['uuid' => Uuid::uuid4()->getBytes(), 'basket_name' => self::PREFIX . 'basket3']);
            $snapshot = BasketSnapshot::forBasketFromJson(
                $basket,
                json_encode([
                    'HostTemplate' => [
                        $templateName => (object) [
                            'object_name' => $templateName,
                            'object_type' => 'template',
                        ],
                    ],
                ])
            );

            $diff = new BasketDiff($snapshot, $db);

            $this->assertFalse(
                $diff->hasChangedFor('HostTemplate', $templateName),
                'a template without custom variables/fields of its own must not be "modified" '
                . 'just because the basket omits those keys entirely'
            );
        } finally {
            $host->delete();
        }
    }

    /**
     * Newer baskets always carry customVariables/fields, even as empty arrays. Same
     * template, still must diff as unchanged.
     */
    public function testDiffReportsUnchangedWhenBasketCarriesEmptyFieldsAndCustomVariables(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();
        $templateName = self::PREFIX . 'plain-template';
        $host = IcingaHost::create([
            'object_name' => $templateName,
            'object_type' => 'template',
        ]);
        $host->store($db);

        try {
            $basket = Basket::create(['uuid' => Uuid::uuid4()->getBytes(), 'basket_name' => self::PREFIX . 'basket4']);
            $snapshot = BasketSnapshot::forBasketFromJson(
                $basket,
                json_encode([
                    'HostTemplate' => [
                        $templateName => (object) [
                            'object_name' => $templateName,
                            'object_type' => 'template',
                            'customVariables' => [],
                            'fields' => [],
                        ],
                    ],
                ])
            );

            $diff = new BasketDiff($snapshot, $db);

            $this->assertFalse(
                $diff->hasChangedFor('HostTemplate', $templateName),
                'a template without custom variables/fields of its own must not be "modified" '
                . 'just because the basket carries those keys as empty arrays'
            );
        } finally {
            $host->delete();
        }
    }

    /**
     * A basket keeps whatever order and keys it was saved with, but the live
     * template always comes back alphabetized with required filled in. Still
     * the same properties either way.
     */
    public function testDiffReportsUnchangedWhenCustomVariableOrderOrRequiredDiffer(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();
        $dba = $db->getDbAdapter();
        $templateName = self::PREFIX . 'multi-prop-template';

        $host = IcingaHost::create([
            'object_name' => $templateName,
            'object_type' => 'template',
        ]);
        $host->store($db);

        // "host" sorts after "env", so the live template won't list them this way
        $propHost = DirectorProperty::create([
            'uuid'       => Uuid::uuid4()->getBytes(),
            'key_name'   => self::PREFIX . 'host',
            'value_type' => 'string',
            'label'      => 'Host',
        ], $db);
        $propHost->store();

        $propEnv = DirectorProperty::create([
            'uuid'       => Uuid::uuid4()->getBytes(),
            'key_name'   => self::PREFIX . 'env',
            'value_type' => 'string',
            'label'      => 'Env',
        ], $db);
        $propEnv->store();

        $db->insert('icinga_host_property', [
            'property_uuid' => DbUtil::quoteBinaryCompat($propHost->get('uuid'), $dba),
            'host_uuid'     => DbUtil::quoteBinaryCompat($host->get('uuid'), $dba),
        ]);
        $db->insert('icinga_host_property', [
            'property_uuid' => DbUtil::quoteBinaryCompat($propEnv->get('uuid'), $dba),
            'host_uuid'     => DbUtil::quoteBinaryCompat($host->get('uuid'), $dba),
        ]);

        $hostUuid = Uuid::fromBytes($propHost->get('uuid'))->toString();
        $envUuid = Uuid::fromBytes($propEnv->get('uuid'))->toString();

        try {
            $basket = Basket::create(['uuid' => Uuid::uuid4()->getBytes(), 'basket_name' => self::PREFIX . 'basket5']);
            $snapshot = BasketSnapshot::forBasketFromJson(
                $basket,
                json_encode([
                    'HostTemplate' => [
                        $templateName => (object) [
                            'object_name' => $templateName,
                            'object_type' => 'template',
                            // attachment order, no 'required', that's how old baskets saved it
                            'customVariables' => [
                                (object) ['property_uuid' => $hostUuid],
                                (object) ['property_uuid' => $envUuid],
                            ],
                        ],
                    ],
                    'CustomVariable' => [
                        $hostUuid => $propHost->export(),
                        $envUuid => $propEnv->export(),
                    ],
                ])
            );

            $diff = new BasketDiff($snapshot, $db);

            $this->assertFalse(
                $diff->hasChangedFor('HostTemplate', $templateName),
                'a differently ordered customVariables list, or one without "required", '
                . 'must not be reported as "modified" when the same properties are attached'
            );
        } finally {
            $dba->delete(
                'icinga_host_property',
                $dba->quoteInto('host_uuid = ?', DbUtil::quoteBinaryCompat($host->get('uuid'), $dba))
            );
            $host->delete();
            $dba->delete('director_property', $dba->quoteInto('key_name = ?', self::PREFIX . 'host'));
            $dba->delete('director_property', $dba->quoteInto('key_name = ?', self::PREFIX . 'env'));
        }
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
