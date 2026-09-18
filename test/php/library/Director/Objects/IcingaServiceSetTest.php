<?php

// SPDX-FileCopyrightText: 2018 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Tests\Icinga\Module\Director\Objects;

use Icinga\Module\Director\Objects\IcingaService;
use Icinga\Module\Director\Objects\IcingaServiceSet;
use Icinga\Module\Director\Test\IcingaObjectTestCase;
use Ramsey\Uuid\Uuid;

class IcingaServiceSetTest extends IcingaObjectTestCase
{
    protected $table = 'icinga_service_set';
    protected $testObjectName = '___TEST___set';

    public function setUp(): void
    {
        parent::setUp();
        $this->assertNull($this->subject, 'subject must have been taken down before!');

        if ($this->hasDb()) {
            $this->subject = IcingaServiceSet::create(array(
                'object_name' => $this->testObjectName,
                'object_type' => 'template',
            ));
            $this->subject->store($this->getDb());
        }
    }

    public function testUpdatingSet()
    {
        $set = IcingaServiceSet::load($this->testObjectName, $this->getDb());
        $this->assertTrue($set->hasBeenLoadedFromDb());

        $set->set('description', 'This is a set created by Phpunit!');
        $this->assertTrue($set->hasBeenModified());
        $set->store();

        $set->set('assign_filter', 'host.name=foobar');
        $this->assertTrue($set->hasBeenModified());
        $set->store();

        $this->assertFalse($set->hasBeenModified());
    }

    public function testAddingSetToHost()
    {
        $host = $this->createObject('for_set', 'icinga_host', array(
            'object_type' => 'object',
            'address'     => '1.2.3.4',
        ));

        $set = IcingaServiceSet::create(array(
            'object_name' => $this->testObjectName,
            'object_type' => 'object',
        ), $this->getDb()); // TODO: fails if db not set here...

        $set->setImports($this->testObjectName);
        $this->assertTrue($set->hasBeenModified());
        $this->assertEquals(array($this->testObjectName), $set->getImports());

        $set->set('host', $host->getObjectName());

        $set->store();
        $this->prepareObjectTearDown($set);
        $this->assertFalse($set->hasBeenModified());
    }

    public function testDeletingHostWithSet()
    {
        $this->createObject('for_set', 'icinga_host', array(
            'object_type' => 'object',
            'address'     => '1.2.3.4',
        ), false)->store();

        $host = $this->loadObject('for_set', 'icinga_host');
        $host->delete();

        $this->checkForDanglingHostSets();
    }

    public function testAddingServicesToSet()
    {
        $set = IcingaServiceSet::load($this->testObjectName, $this->getDb());

        // TODO: setting service_set by name should work too...

        $serviceA = $this->createObject('serviceA', 'icinga_service', array(
            'object_type'    => 'apply',
            'service_set_id' => $set->getAutoincId(),
        ));
        $nameA = $serviceA->getObjectName();

        $serviceB = $this->createObject('serviceB', 'icinga_service', array(
            'object_type'    => 'apply',
            'service_set_id' => $set->getAutoincId(),
        ));
        $nameB = $serviceB->getObjectName();

        $services = $set->getServiceObjects();

        $this->assertCount(2, $services);
        $this->assertArrayHasKey($nameA, $services);
        $this->assertArrayHasKey($nameB, $services);
        $this->assertEquals($serviceA->getAutoincId(), $services[$nameA]->getAutoincId());
        $this->assertEquals($serviceB->getAutoincId(), $services[$nameB]->getAutoincId());

        // TODO: deleting set should delete services

        $this->checkForDanglingServices();
    }

    /**
     * Verify that importing the same UUID updates the existing member in place
     *
     * @return void
     */
    public function testImportingMemberWithSameUuidUpdatesInPlace(): void
    {
        if (! $this->hasDb()) {
            $this->markTestSkipped('Test db not configured');
        }

        $db = $this->getDb();
        $set = IcingaServiceSet::load($this->testObjectName, $db);
        $setId = $set->getAutoincId();
        $name = '___TEST___member_same_uuid';

        $member = IcingaService::create([
            'object_type'    => 'apply',
            'object_name'    => $name,
            'service_set_id' => $setId,
            'vars'           => ['marker' => 'original'],
        ], $db);
        $uuid = $member->getUniqueId();
        $member->store();

        try {
            $set->setServices([
                (object) [
                    'object_type' => 'apply',
                    'object_name' => $name,
                    'uuid'        => $uuid->toString(),
                    'vars'        => (object) ['marker' => 'updated'],
                ],
            ]);
            $set->store();

            $ids = $this->fetchSetMemberIds($setId, $name);
            $this->assertCount(1, $ids, 'A member imported with the same UUID must not be duplicated');
            $stored = IcingaService::loadWithAutoIncId($ids[0], $db);
            $this->assertSame($member->getAutoincId(), $stored->getAutoincId());
            $this->assertSame($uuid->toString(), $stored->getUniqueId()->toString());
            $this->assertSame('updated', $stored->vars()->marker->getValue());
        } finally {
            $this->cleanupServices($this->fetchSetMemberIds($setId, $name));
        }
    }

    /**
     * Verify that importing a new UUID replaces the old member and its properties
     *
     * @return void
     */
    public function testImportingMemberWithNewUuidDoesNotDuplicate(): void
    {
        if (! $this->hasDb()) {
            $this->markTestSkipped('Test db not configured');
        }

        $db = $this->getDb();
        $set = IcingaServiceSet::load($this->testObjectName, $db);
        $setId = $set->getAutoincId();
        $name = '___TEST___member_new_uuid';

        $existing = IcingaService::create([
            'object_type'    => 'apply',
            'object_name'    => $name,
            'service_set_id' => $setId,
            'vars'           => ['marker' => 'original'],
        ], $db);
        $uuidA = $existing->getUniqueId();
        $existing->store();

        try {
            $uuidB = Uuid::uuid4();
            $this->assertNotEquals($uuidA->toString(), $uuidB->toString());

            $set->setServices([
                (object) [
                    'object_type' => 'apply',
                    'object_name' => $name,
                    'uuid'        => $uuidB->toString(),
                    'vars'        => (object) ['marker' => 'updated'],
                ],
            ]);
            $set->store();

            $ids = $this->fetchSetMemberIds($setId, $name);
            $this->assertCount(1, $ids, 'A member imported with a new UUID must exist exactly once');
            $stored = IcingaService::loadWithAutoIncId($ids[0], $db);
            $this->assertSame($uuidB->toString(), $stored->getUniqueId()->toString());
            $this->assertSame('updated', $stored->vars()->marker->getValue());
        } finally {
            $this->cleanupServices($this->fetchSetMemberIds($setId, $name));
        }
    }

    /**
     * Fetch IDs of all matching members, including duplicate names
     *
     * @param int|string $setId
     * @param string $name
     *
     * @return list<int|string>
     */
    private function fetchSetMemberIds(int|string $setId, string $name): array
    {
        $db = $this->getDb()->getDbAdapter();

        return $db->fetchCol(
            $db->select()
                ->from('icinga_service', 'id')
                ->where('service_set_id = ?', $setId)
                ->where('object_name = ?', $name)
        );
    }

    /**
     * Delete the test members through their normal object lifecycle
     *
     * @param list<int|string> $ids
     *
     * @return void
     */
    private function cleanupServices(array $ids): void
    {
        foreach ($ids as $id) {
            IcingaService::loadWithAutoIncId($id, $this->getDb())->delete();
        }
    }

    public function testCreatingSetWithoutType()
    {
        $this->expectException(\RuntimeException::class);

        $set = IcingaServiceSet::create(array(
            'object_name' => '___TEST__set_BAD',
        ));
        $set->store($this->getDb());
    }

    public function testCreatingServiceSetWithoutHost()
    {
        $this->expectException(\InvalidArgumentException::class);

        $set = IcingaServiceSet::create(array(
            'object_name' => '___TEST__set_BAD2',
            'object_type' => 'object',
        ));

        $set->store($this->getDb());
    }

    public function testDeletingSet()
    {
        $set = IcingaServiceSet::load($this->testObjectName, $this->getDb());
        $set->delete();

        $this->assertFalse(IcingaServiceSet::exists($this->testObjectName, $this->getDb()));
        $this->subject = null;
    }

    public function checkForDanglingServices()
    {
        $db = $this->getDb()->getDbAdapter();
        $query = $db->select()
            ->from(array('s' => 'icinga_service'), array('id'))
            ->joinLeft(
                array('ss' => 'icinga_service_set'),
                'ss.id = s.service_set_id',
                array()
            )
            ->where('s.service_set_id IS NOT NULL')
            ->where('ss.id IS NULL');

        $ids = $db->fetchCol($query);

        $this->assertEmpty($ids, sprintf('Found dangling service_set services in database: %s', join(', ', $ids)));
    }

    public function checkForDanglingHostSets()
    {
        $db = $this->getDb()->getDbAdapter();
        $query = $db->select()
            ->from(array('ss' => 'icinga_service_set'), array('id'))
            ->joinLeft(
                array('h' => 'icinga_host'),
                'h.id = ss.host_id',
                array()
            )
            ->where('ss.host_id IS NOT NULL')
            ->where('h.id IS NULL');

        $ids = $db->fetchCol($query);

        $this->assertEmpty(
            $ids,
            sprintf(
                'Found dangling service_set\'s for a host, without the host in database: %s',
                join(', ', $ids)
            )
        );
    }
}
