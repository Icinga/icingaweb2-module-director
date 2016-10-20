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
    protected $createdServices = [];

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
        $service->delete();
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
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();

        $service = $this->service();

        // Service apply rule rendering requires access to settings:
        $service->setConnection($db);
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
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();

        $service = $this->service();
        // Service apply rule rendering requires access to settings:
        $service->setConnection($db);
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

        $service->delete();
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

        $service->delete();
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
        $masterzone = $db->getMasterZoneName();
        $this->assertEquals(
            array('zones.d/' . $masterzone . '/services.conf'),
            $config->getFileNames()
        );
    }

    public function testVariablesInPropertiesAndCustomVariables()
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();

        $service = $this->service('___TEST___service_$not_replaced$');
        $service->object_type = 'apply';
        $service->display_name = 'Service: $host.vars.replaced$';
        $service->assignments = array(
            'host.address="127.*"',
        );
        $service->{'vars.custom_var'} = '$host.vars.replaced$';
        $service->store($db);

        $service = IcingaService::loadWithAutoIncId($service->id, $db);
        $this->assertEquals(
            $this->loadRendered('service3'),
            (string) $service
        );
    }

    public function testVariablesAreNotReplacedForNonApplyObjects()
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();

        $service = $this->service('___TEST___service_$not_replaced$');
        $service->object_type = 'object';
        $service->display_name = 'Service: $host.vars.not_replaced$';
        $service->{'vars.custom_var'} = '$host.vars.not_replaced$';
        $service->store($db);

        $service = IcingaService::loadWithAutoIncId($service->id, $db);
        $this->assertEquals(
            $this->loadRendered('service4'),
            (string) $service
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

    protected function service($objectName = null)
    {
        if ($objectName === null) {
            $objectName = $this->testServiceName;
        }
        $this->createdServices[] = $objectName;
        return IcingaService::create(array(
            'object_name'  => $objectName,
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

            $kill = $this->createdServices;
            foreach ($kill as $name) {
                if (IcingaService::exists(array($name), $db)) {
                    IcingaService::load($name, $db)->delete();
                }
            }
        }
    }
}
