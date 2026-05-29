<?php

// SPDX-FileCopyrightText: 2026 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Tests\Icinga\Module\Director\Objects;

use Icinga\Module\Director\Data\Exporter;
use Icinga\Module\Director\Db\DbUtil;
use Icinga\Module\Director\DirectorObject\Automation\BasketSnapshot;
use Icinga\Module\Director\Objects\DirectorProperty;
use Icinga\Module\Director\Objects\IcingaHost;
use Icinga\Module\Director\Test\BaseTestCase;
use Ramsey\Uuid\Uuid;

/**
 * Integration tests for BasketSnapshot round-trip with DirectorProperty (custom variables).
 *
 * Scenario: a host template "linux-server" carries a disk_checks dynamic-dictionary property.
 * Snapshot, wipe, restore, and verify the system returns to its original state.
 */
class BasketSnapshotCustomVariableTest extends BaseTestCase
{
    private const PREFIX = '___TEST___';

    private const TEMPLATE_NAME = self::PREFIX . 'linux-server';
    private const PROP_KEY_NAME = self::PREFIX . 'disk_checks_bk';

    public function testSnapshotIncludesCustomVariableSection(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();
        [$host, $property] = $this->createTemplateWithProperty($db);

        $json = $this->buildSnapshotJson($host, $property, $db);
        $decoded = json_decode($json, true);

        $this->assertArrayHasKey(
            'CustomVariable',
            $decoded,
            'Basket JSON must contain a CustomVariable section'
        );
    }

    public function testRestoreCreatesDirectorProperty(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();
        [$host, $property] = $this->createTemplateWithProperty($db);
        $json = $this->buildSnapshotJson($host, $property, $db);
        $propUuid = Uuid::fromBytes($property->get('uuid'));

        $this->wipeTemplateAndProperty($host, $property, $db);

        BasketSnapshot::restoreJson($json, $db);

        $restored = DirectorProperty::loadWithUniqueId($propUuid, $db);
        $this->assertNotNull($restored, 'director_property row must be created by restore');
        $this->assertEquals(self::PROP_KEY_NAME, $restored->get('key_name'));
        $this->assertEquals('dynamic-dictionary', $restored->get('value_type'));
    }

    public function testRestoreBindsPropertyToTemplate(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();
        [$host, $property] = $this->createTemplateWithProperty($db);
        $json = $this->buildSnapshotJson($host, $property, $db);

        $this->wipeTemplateAndProperty($host, $property, $db);

        BasketSnapshot::restoreJson($json, $db);

        $restoredHost = IcingaHost::load(self::TEMPLATE_NAME, $db);
        $restoredProp = DirectorProperty::loadWithUniqueId(
            Uuid::fromBytes($property->get('uuid')),
            $db
        );

        $dba = $db->getDbAdapter();
        $count = $dba->fetchOne(
            $dba->select()
                ->from('icinga_host_property', ['cnt' => 'COUNT(*)'])
                ->where(
                    'host_uuid = ?',
                    DbUtil::quoteBinaryCompat(DbUtil::binaryResult($restoredHost->get('uuid')), $dba)
                )
                ->where(
                    'property_uuid = ?',
                    DbUtil::quoteBinaryCompat(DbUtil::binaryResult($restoredProp->get('uuid')), $dba)
                )
        );

        $this->assertEquals(1, (int) $count, 'icinga_host_property binding must be restored');
    }

    public function testRestoreChildItemsForDictionary(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();
        [$host, $property] = $this->createTemplateWithProperty($db);

        // Add child fields to the dictionary
        foreach (['mount_point', 'warn', 'crit'] as $field) {
            DirectorProperty::create([
                'uuid'        => Uuid::uuid4()->getBytes(),
                'key_name'    => $field,
                'parent_uuid' => $property->get('uuid'),
                'value_type'  => 'string',
            ], $db)->store();
        }

        // Re-load to pick up the fresh items
        $property = DirectorProperty::loadWithUniqueId(
            Uuid::fromBytes($property->get('uuid')),
            $db
        );

        $json = $this->buildSnapshotJson($host, $property, $db);

        $this->wipeTemplateAndProperty($host, $property, $db);

        BasketSnapshot::restoreJson($json, $db);

        $restored = DirectorProperty::loadWithUniqueId(
            Uuid::fromBytes($property->get('uuid')),
            $db
        );
        $childKeys = array_map(
            fn($c) => $c->get('key_name'),
            $restored->fetchItemsFromDb()
        );
        sort($childKeys);

        $this->assertEquals(
            ['crit', 'mount_point', 'warn'],
            $childKeys,
            'All child items must be restored for the dictionary property'
        );
    }

    public function testRestoreIsIdempotent(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();
        [$host, $property] = $this->createTemplateWithProperty($db);
        $json = $this->buildSnapshotJson($host, $property, $db);

        $this->wipeTemplateAndProperty($host, $property, $db);

        BasketSnapshot::restoreJson($json, $db);
        BasketSnapshot::restoreJson($json, $db);

        $dba = $db->getDbAdapter();
        $propCount = $dba->fetchOne(
            $dba->select()
                ->from('director_property', ['cnt' => 'COUNT(*)'])
                ->where('uuid = ?', DbUtil::quoteBinaryCompat(DbUtil::binaryResult($property->get('uuid')), $dba))
        );

        $this->assertEquals(1, (int) $propCount, 'Restoring twice must not create duplicate properties');
    }

    public function testBasketsWithoutCustomPropertiesStillWork(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();

        // Basket with a host template that has no custom properties key at all
        $templateName = self::PREFIX . 'no-props-template';
        $json = json_encode([
            'HostTemplate' => [
                $templateName => (object) [
                    'object_name' => $templateName,
                    'object_type' => 'template',
                ]
            ]
        ]);

        $this->expectNotToPerformAssertions();
        BasketSnapshot::restoreJson($json, $db);

        // Cleanup
        if (IcingaHost::exists($templateName, $db)) {
            IcingaHost::load($templateName, $db)->delete();
        }
    }

    protected function tearDown(): void
    {
        if ($this->hasDb()) {
            $db = $this->getDb();
            $dba = $db->getDbAdapter();

            if (IcingaHost::exists(self::TEMPLATE_NAME, $db)) {
                $host = IcingaHost::load(self::TEMPLATE_NAME, $db);
                $dba->delete(
                    'icinga_host_property',
                    $dba->quoteInto(
                        'host_uuid = ?',
                        DbUtil::quoteBinaryCompat(DbUtil::binaryResult($host->get('uuid')), $dba)
                    )
                );
                $host->delete();
            }

            $rows = $dba->fetchAll(
                $dba->select()->from('director_property', ['uuid'])->where('key_name = ?', self::PROP_KEY_NAME)
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

            $dba->delete('director_property', $dba->quoteInto('key_name = ?', self::PROP_KEY_NAME));
        }

        parent::tearDown();
    }

    /**
     * @return array{IcingaHost, DirectorProperty}
     */
    private function createTemplateWithProperty($db): array
    {
        if (IcingaHost::exists(self::TEMPLATE_NAME, $db)) {
            $host = IcingaHost::load(self::TEMPLATE_NAME, $db);
        } else {
            $host = IcingaHost::create([
                'object_name' => self::TEMPLATE_NAME,
                'object_type' => 'template',
            ]);
            $host->store($db);
        }

        $dba = $db->getDbAdapter();
        $dba->delete('director_property', $dba->quoteInto('key_name = ?', self::PROP_KEY_NAME));

        $property = DirectorProperty::create([
            'uuid'       => Uuid::uuid4()->getBytes(),
            'key_name'   => self::PROP_KEY_NAME,
            'value_type' => 'dynamic-dictionary',
            'label'      => 'Disk Checks',
        ], $db);
        $property->store();

        $dba = $db->getDbAdapter();
        $db->insert('icinga_host_property', [
            'property_uuid' => DbUtil::quoteBinaryCompat($property->get('uuid'), $dba),
            'host_uuid'     => DbUtil::quoteBinaryCompat($host->get('uuid'), $dba),
        ]);

        return [$host, $property];
    }

    private function buildSnapshotJson(IcingaHost $host, DirectorProperty $property, $db): string
    {
        $exporter = new Exporter($db);
        $exportedHost = $exporter->export($host);

        $exportedProperty = $property->export();
        $propertyUuid = Uuid::fromBytes($property->get('uuid'))->toString();

        $snapshot = [
            'HostTemplate' => [
                self::TEMPLATE_NAME => $exportedHost,
            ],
            'CustomVariable' => [
                $propertyUuid => $exportedProperty,
            ],
        ];

        return json_encode($snapshot);
    }

    private function wipeTemplateAndProperty(IcingaHost $host, DirectorProperty $property, $db): void
    {
        $dba = $db->getDbAdapter();
        $quotedHostUuid = DbUtil::quoteBinaryCompat(DbUtil::binaryResult($host->get('uuid')), $dba);
        $quotedPropUuid = DbUtil::quoteBinaryCompat(DbUtil::binaryResult($property->get('uuid')), $dba);

        $dba->delete('icinga_host_property', $dba->quoteInto('host_uuid = ?', $quotedHostUuid));
        $dba->delete('director_property', $dba->quoteInto('parent_uuid = ?', $quotedPropUuid));
        $dba->delete('director_property', $dba->quoteInto('uuid = ?', $quotedPropUuid));

        IcingaHost::load(self::TEMPLATE_NAME, $db)->delete();
    }
}
