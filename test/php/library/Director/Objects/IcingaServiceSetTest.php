<?php

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
     * Control case: importing a Set member with an unchanged UUID must match the
     * existing service by UUID and update it in place - never create a second row.
     */
    public function testImportingMemberWithSameUuidUpdatesInPlace()
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
        ], $db);
        $uuid = $member->getUniqueId();
        $member->store();

        // Import the same member (same UUID), as an unchanged Basket would
        $set->setServices([
            (object) [
                'object_type' => 'apply',
                'object_name' => $name,
                'uuid'        => $uuid->toString(),
            ],
        ]);
        $set->store();

        $ids = $this->fetchSetMemberIds($setId, $name);
        $this->cleanupServices($ids);

        $this->assertCount(1, $ids, 'A member imported with the same UUID must not be duplicated');
    }

    /**
     * Regression test: importing a Set member with a *new* UUID (as a regenerated
     * Basket does) must not leave a duplicate behind.
     *
     * IcingaServiceSet::setServices() matches members by UUID only, so a changed UUID
     * creates a new service. storeRelatedServices() is expected to delete the now
     * orphaned old member, but it enumerates via the name-deduplicating fetchServices()
     * (ServiceSetQueryBuilder::fetchServicesWithQuery(), which keys results by
     * object_name). Two same-named rows collapse into one, hiding the orphan from the
     * deletion loop, so it survives and the member ends up duplicated.
     */
    public function testImportingMemberWithNewUuidDoesNotDuplicate()
    {
        if (! $this->hasDb()) {
            $this->markTestSkipped('Test db not configured');
        }

        $db = $this->getDb();
        $set = IcingaServiceSet::load($this->testObjectName, $db);
        $setId = $set->getAutoincId();
        $name = '___TEST___member_new_uuid';

        // Member as stored from an earlier Basket revision (UUID A)
        $existing = IcingaService::create([
            'object_type'    => 'apply',
            'object_name'    => $name,
            'service_set_id' => $setId,
        ], $db);
        $uuidA = $existing->getUniqueId();
        $existing->store();

        // Same member, imported carrying a different UUID (B)
        $uuidB = Uuid::uuid4();
        $this->assertNotEquals($uuidA->toString(), $uuidB->toString());

        $set->setServices([
            (object) [
                'object_type' => 'apply',
                'object_name' => $name,
                'uuid'        => $uuidB->toString(),
            ],
        ]);
        $set->store();

        $ids = $this->fetchSetMemberIds($setId, $name);
        $this->cleanupServices($ids);

        $this->assertCount(1, $ids, 'A member imported with a new UUID must exist exactly once');
    }

    private function fetchSetMemberIds($setId, $name)
    {
        $db = $this->getDb()->getDbAdapter();

        return $db->fetchCol(
            $db->select()
                ->from('icinga_service', 'id')
                ->where('service_set_id = ?', $setId)
                ->where('object_name = ?', $name)
        );
    }

    private function cleanupServices(array $ids)
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
