<?php

namespace Tests\Icinga\Module\Director\Objects;

use Icinga\Module\Director\Objects\IcingaServiceSet;
use Icinga\Module\Director\Test\IcingaObjectTestCase;

class IcingaServiceSetTest extends IcingaObjectTestCase
{
    protected $table = 'icinga_service_set';
    protected $testObjectName = '___TEST___set';

    public function setUp(): void
    {
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
