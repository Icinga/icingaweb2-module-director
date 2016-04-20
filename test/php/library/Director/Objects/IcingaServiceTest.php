<?php

namespace Tests\Icinga\Module\Director\Objects;

use Icinga\Module\Director\IcingaConfig\IcingaConfig;
use Icinga\Module\Director\Objects\IcingaHost;
use Icinga\Module\Director\Objects\IcingaService;
use Icinga\Module\Director\Test\BaseTestCase;

class IcingaServiceTest extends BaseTestCase
{
    protected $testHostName = '___TEST___host';

    protected $testServiceName = '___TEST___service';

    public function testUnstoredHostCanBeLazySet()
    {
        $service = $this->service();
        $service->display_name = 'Something else';
        $service->host = 'not yet';
        $this->assertEquals(
            'not yet',
            $service->host
        );
    }

    /**
     * @expectedException \Icinga\Exception\NotFoundError
     */
    public function testFailsToStoreWithMissingLazyRelations()
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();
        $service = $this->service();
        $service->display_name = 'Something else';
        $service->host = 'not yet';
        $service->store($db);
    }

    public function testAcceptsAssignRules()
    {
        $service = $this->service();
        $service->object_type = 'apply';
        $service->assignments = array(
            'host.address="127.*"'
        );
    }

    /**
     * @expectedException Icinga\Exception\ProgrammingError
     */
    public function testRefusesAssignRulesWhenNotBeingAnApply()
    {
        $service = $this->service();
        $service->assignments = array(
            'host.address=127.*'
        );
    }

    public function testAcceptsAndRendersFlatAssignRules()
    {
        $service = $this->service();
        $service->object_type = 'apply';
        $service->assignments = array(
            'host.address="127.*"',
            'host.vars.env="test"'
        );

        $this->assertEquals(
            $this->loadRendered('service1'),
            (string) $service
        );

        $this->assertEquals(
            'host.address="127.*"',
            $service->toPlainObject()->assignments['assign'][0]
        );
    }

    public function testAcceptsAndRendersStructuredAssignRules()
    {
        $service = $this->service();
        $service->object_type = 'apply';
        $service->assignments = array(
            'host.address="127.*"',
            'host.vars.env="test"'
        );

        $this->assertEquals(
            $this->loadRendered('service1'),
            (string) $service
        );

        $this->assertEquals(
            'host.address="127.*"',
            $service->toPlainObject()->assignments['assign'][0]
        );
    }

    public function testPersistsAssignRules()
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();

        $service = $this->service();
        $service->object_type = 'apply';
        $service->assignments = array(
            'host.address="127.*"',
            'host.vars.env="test"'
        );
        $service->store($db);

        $service = IcingaService::loadWithAutoIncId($service->id, $db);
        $this->assertEquals(
            $this->loadRendered('service1'),
            (string) $service
        );

        $this->assertEquals(
            'host.address="127.*"',
            $service->toPlainObject()->assignments['assign'][0]
        );
    }

    public function testStaysUnmodifiedWhenSameFiltersAreSetInDifferentWays()
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();

        $service = $this->service();
        $service->object_type = 'apply';
        $service->assignments = 'host.address="127.*"';
        $service->store($db);
        $this->assertFalse($service->hasBeenModified());

        $service->assignments = array(
            'host.address="127.*"',
        );
        $this->assertFalse($service->hasBeenModified());

        $service->assignments = 'host.address="128.*"';
        $this->assertTrue($service->hasBeenModified());

        $service->store();
        $this->assertFalse($service->hasBeenModified());

        $service->assignments = array('assign' => 'host.address="128.*"');
        $this->assertFalse($service->hasBeenModified());

        $service->assignments = array(
            'assign' => array(
                'host.address="128.*"'
             )
        );

        $this->assertFalse($service->hasBeenModified());

        $service->assignments = array(
            'assign' => array(
                'host.address="128.*"'
            ),
            'ignore' => 'host.name="localhost"'
        );

        $this->assertTrue($service->hasBeenModified());

        $service->store();
        $service = IcingaService::loadWithAutoIncId($service->id, $db);

        $this->assertEquals(
            'host.address="128.*"',
            $service->toPlainObject()->assignments['assign'][0]
        );

        $this->assertEquals(
            'host.name="localhost"',
            $service->toPlainObject()->assignments['ignore'][0]
        );

        $this->assertEquals(
            $this->loadRendered('service2'),
            (string) $service
        );
    }

    public function testRendersToTheCorrectZone()
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();
        $service = $this->service()->setConnection($db);

        $config = new IcingaConfig($db);
        $service->renderToConfig($config);
        $this->assertEquals(
            array('zones.d/master.conf'),
            $config->getFileNames()
        );
    }

    protected function host()
    {
        return IcingaHost::create(array(
            'object_name'  => $this->testHostName,
            'object_type'  => 'object',
            'address'      => '127.0.0.1',
        ));
    }

    protected function service()
    {
        return IcingaService::create(array(
            'object_name'  => $this->testServiceName,
            'object_type'  => 'object',
            'display_name' => 'Whatever service',
            'vars'         => array(
                'test1' => 'string',
                'test2' => 17,
                'test3' => false,
                'test4' => (object) array(
                    'this' => 'is',
                    'a' => array(
                        'dict',
                        'ionary'
                    )
                )
            )
        ));
    }

    protected function loadRendered($name)
    {
        return file_get_contents(__DIR__ . '/rendered/' . $name . '.out');
    }

    public function tearDown()
    {
        if ($this->hasDb()) {
            $db = $this->getDb();
            $kill = array($this->testHostName);
            foreach ($kill as $name) {
                if (IcingaHost::exists($name, $db)) {
                    IcingaHost::load($name, $db)->delete();
                }
            }

            $kill = array($this->testServiceName);
            foreach ($kill as $name) {
                if (IcingaService::exists(array($name), $db)) {
                    IcingaService::load($name, $db)->delete();
                }
            }
        }
    }
}
