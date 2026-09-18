<?php

// SPDX-FileCopyrightText: 2026 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Tests\Icinga\Module\Director\Objects;

use Icinga\Module\Director\Data\Db\DbObject;
use Icinga\Module\Director\Data\Db\DbObjectStore;
use Icinga\Module\Director\Db\Branch\Branch;
use Icinga\Module\Director\Exception\NestingError;
use Icinga\Module\Director\Objects\IcingaHost;
use Icinga\Module\Director\Objects\IcingaZone;
use Icinga\Module\Director\Test\BaseTestCase;
use ReflectionProperty;

/**
 * Zone hierarchy validation and recovery through the branch-aware loader
 */
class IcingaZoneTest extends BaseTestCase
{
    private ?ReflectionProperty $objectStoreProperty = null;

    private ?DbObjectStore $originalObjectStore = null;

    private bool $transactionStarted = false;

    public function setUp(): void
    {
        parent::setUp();
        $this->skipForMissingDb();

        $db = $this->getDb();
        $db->getDbAdapter()->beginTransaction();
        $this->transactionStarted = true;

        // The public setter cannot restore the default null store.
        $this->objectStoreProperty = new ReflectionProperty(DbObject::class, 'dbObjectStore');
        $this->originalObjectStore = $this->objectStoreProperty->getValue();
        DbObject::setDbObjectStore(new DbObjectStore($db, new Branch()));
    }

    public function tearDown(): void
    {
        try {
            if ($this->transactionStarted) {
                $this->getDb()->getDbAdapter()->rollBack();
            }
        } finally {
            if ($this->objectStoreProperty !== null) {
                $this->objectStoreProperty->setValue(null, $this->originalObjectStore);
            }

            parent::tearDown();
        }
    }

    public function testParentSurvivesReload(): void
    {
        $parent = $this->createZone('parent');
        $child = $this->createZone('child', $parent);

        $loaded = IcingaZone::load($child->getObjectName(), $this->getDb());

        self::assertEquals($parent->get('id'), $loaded->get('parent_id'));
        self::assertSame($parent->getObjectName(), $loaded->get('parent'));
    }

    public function testHostZoneSurvivesReload(): void
    {
        $zone = $this->createZone('host-zone');
        $host = IcingaHost::create([
            'object_name' => '___TEST___zone-host',
            'object_type' => 'object',
            'zone_id'     => $zone->get('id'),
        ], $this->getDb());
        $host->store();

        $loaded = IcingaHost::load($host->getObjectName(), $this->getDb());

        self::assertEquals($zone->get('id'), $loaded->get('zone_id'));
        self::assertSame($zone->getObjectName(), $loaded->get('zone'));
    }

    public function testNamedParentSurvivesCreationAndReload(): void
    {
        $parent = $this->createZone('parent');
        $child = IcingaZone::create([
            'object_name' => '___TEST___zone-child',
            'object_type' => 'object',
            'parent'      => $parent->getObjectName(),
        ], $this->getDb());
        $child->store();

        $loaded = IcingaZone::load($child->getObjectName(), $this->getDb());

        self::assertEquals($parent->get('id'), $loaded->get('parent_id'));
        self::assertSame($parent->getObjectName(), $loaded->get('parent'));
    }

    public function testRejectsSelfAsParent(): void
    {
        $zone = $this->createZone('self');
        $zone = IcingaZone::load($zone->getObjectName(), $this->getDb());
        $zone->set('parent_id', $zone->get('id'));

        $this->expectException(NestingError::class);
        $this->expectExceptionMessage('Loop detected: ___TEST___zone-self -> ___TEST___zone-self');

        $zone->store();
    }

    public function testRejectsDescendantAsParent(): void
    {
        $root = $this->createZone('root');
        $child = $this->createZone('child', $root);
        $grandchild = $this->createZone('grandchild', $child);
        $root = IcingaZone::load($root->getObjectName(), $this->getDb());
        $root->set('parent_id', $grandchild->get('id'));

        $this->expectException(NestingError::class);
        $this->expectExceptionMessage(
            'Loop detected: ___TEST___zone-root -> ___TEST___zone-grandchild'
            . ' -> ___TEST___zone-child -> ___TEST___zone-root'
        );

        $root->store();
    }

    public function testRejectsSelfAsNamedParent(): void
    {
        $zone = $this->createZone('self');
        $zone = IcingaZone::load($zone->getObjectName(), $this->getDb());
        $zone->set('parent', $zone->getObjectName());

        $this->expectException(NestingError::class);
        $this->expectExceptionMessage('Loop detected: ___TEST___zone-self -> ___TEST___zone-self');

        $zone->store();
    }

    public function testCanLoadAndRepairExistingSelfLoop(): void
    {
        $zone = $this->createZone('self');
        $this->forceParent($zone, $zone);

        $loaded = IcingaZone::load($zone->getObjectName(), $this->getDb());
        self::assertSame($zone->getObjectName(), $loaded->get('parent'));

        $loaded->set('parent_id', null);
        $loaded->store();

        self::assertNull(IcingaZone::load($zone->getObjectName(), $this->getDb())->get('parent_id'));
    }

    public function testCanLoadAndRepairExistingMultiZoneLoop(): void
    {
        $parent = $this->createZone('parent');
        $child = $this->createZone('child', $parent);
        $this->forceParent($parent, $child);

        $loaded = IcingaZone::load($parent->getObjectName(), $this->getDb());
        self::assertSame($child->getObjectName(), $loaded->get('parent'));

        $loaded->set('parent_id', null);
        $loaded->store();

        self::assertNull(IcingaZone::load($parent->getObjectName(), $this->getDb())->get('parent_id'));
        self::assertSame(
            $parent->getObjectName(),
            IcingaZone::load($child->getObjectName(), $this->getDb())->get('parent')
        );
    }

    public function testRejectsParentChainEnteringExistingLoop(): void
    {
        $parent = $this->createZone('parent');
        $child = $this->createZone('child', $parent);
        $this->forceParent($parent, $child);
        $zone = $this->createZone('outside');
        $zone = IcingaZone::load($zone->getObjectName(), $this->getDb());
        $zone->set('parent_id', $parent->get('id'));

        $this->expectException(NestingError::class);
        $this->expectExceptionMessage(
            'Loop detected: ___TEST___zone-outside -> ___TEST___zone-parent'
            . ' -> ___TEST___zone-child -> ___TEST___zone-parent'
        );

        $zone->store();
    }

    public function testRejectsNewZoneEnteringExistingLoop(): void
    {
        $parent = $this->createZone('parent');
        $child = $this->createZone('child', $parent);
        $this->forceParent($parent, $child);

        $this->expectException(NestingError::class);
        $this->expectExceptionMessage(
            'Loop detected: ___TEST___zone-outside -> ___TEST___zone-parent'
            . ' -> ___TEST___zone-child -> ___TEST___zone-parent'
        );

        $this->createZone('outside', $parent);
    }

    public function testRejectsNewZoneWithNamedParentInExistingLoop(): void
    {
        $parent = $this->createZone('parent');
        $this->forceParent($parent, $parent);
        $zone = IcingaZone::create([
            'object_name' => '___TEST___zone-outside',
            'object_type' => 'object',
            'parent'      => $parent->getObjectName(),
        ], $this->getDb());

        $this->expectException(NestingError::class);
        $this->expectExceptionMessage(
            'Loop detected: ___TEST___zone-outside -> ___TEST___zone-parent -> ___TEST___zone-parent'
        );

        $zone->store();
    }

    /**
     * Create a stored zone in the current test transaction
     *
     * @param string $suffix
     * @param ?IcingaZone $parent
     *
     * @return IcingaZone
     */
    private function createZone(string $suffix, ?IcingaZone $parent = null): IcingaZone
    {
        $zone = IcingaZone::create([
            'object_name' => '___TEST___zone-' . $suffix,
            'object_type' => 'object',
            'parent_id'   => $parent === null ? null : $parent->get('id'),
        ], $this->getDb());
        $zone->store();

        return $zone;
    }

    /**
     * Reproduce a hierarchy stored before cycle validation was introduced
     *
     * @param IcingaZone $zone
     * @param IcingaZone $parent
     *
     * @return void
     */
    private function forceParent(IcingaZone $zone, IcingaZone $parent): void
    {
        $this->getDb()->getDbAdapter()->update(
            'icinga_zone',
            ['parent_id' => $parent->get('id')],
            ['id = ?' => $zone->get('id')]
        );
    }
}
