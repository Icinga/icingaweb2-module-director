<?php

namespace Tests\Icinga\Module\Director\Objects;

use Icinga\Data\Db\DbQuery;
use Icinga\Module\Director\Objects\IcingaServiceSet;
use Icinga\Module\Director\Test\IcingaObjectTestCase;

class IcingaServiceSetTestIcinga extends IcingaObjectTestCase
{
    protected $table = 'icinga_service_set';
    protected $testObjectName = '___TEST___set';

    public function setUp()
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

        $set->set('assign_filter','host.name=foobar');
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
        $this->markTestIncomplete('Host deletion fails / does not cleanup sets!');

        $this->testAddingSetToHost();

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
     * @expectedException \Icinga\Exception\ProgrammingError
     */
    public function testCreatingSetWithoutType()
    {
        // TODO: fix error
        $this->markTestIncomplete('Throws a database error, not a proper exception!');

        $set = IcingaServiceSet::create(array(
            'object_name' => '___TEST__set_BAD',
        ));
        $set->store($this->getDb());
    }

    /**
     * @expectedException \Icinga\Exception\ProgrammingError
     */
    public function testCreatingHostSetWithoutHost()
    {
        $this->markTestIncomplete('Throws no error currently, but will create the object');

        /* TODO: fix this, it will create an object currently
        $set = IcingaServiceSet::create(array(
            'object_name' => '___TEST__set_BAD2',
            'object_type' => 'object',
        ));

        $set->store($this->getDb());
        */
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
        $db = $this->getDb();
        /** @var DbQuery $query */
        $query = $db->select()
            ->from('icinga_service as s', array('id'))
            ->joinLeft('icinga_service_set as ss', 'ss.id = s.service_set_id', array())
            ->where('s.service_set_id IS NOT NULL')
            ->where('ss.id IS NULL');

        $ids = $query->fetchColumn();

        $this->assertEmpty($ids, sprintf('Found dangling service_set services in database: %s', join(', ', $ids)));
    }

    public function checkForDanglingHostSets()
    {
        $db = $this->getDb();
        /** @var DbQuery $query */
        $query = $db->select()
            ->from('icinga_service_set as s', array('id'))
            ->joinLeft('icinga_host as h', 'h.id = s.host_id', array())
            ->where('s.host_id IS NOT NULL')
            ->where('h.id IS NULL');

        $ids = $query->fetchColumn();

        $this->assertEmpty($ids,
            sprintf('Found dangling service_set\'s for a host, without the host in database: %s', join(', ', $ids)));
    }
}
