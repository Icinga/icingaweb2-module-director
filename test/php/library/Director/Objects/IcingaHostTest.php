<?php

namespace Tests\Icinga\Module\Director\Objects;

use Icinga\Module\Director\IcingaConfig\IcingaConfig;
use Icinga\Module\Director\Objects\IcingaHost;
use Icinga\Module\Director\Objects\IcingaZone;
use Icinga\Module\Director\Test\BaseTestCase;

class IcingaHostTest extends BaseTestCase
{
    protected $testHostName = '___TEST___host';

    public function testPropertiesCanBeSet()
    {
        $host = $this->host();
        $host->display_name = 'Something else';
        $this->assertEquals(
            $host->display_name,
            'Something else'
        );
    }

    public function testCanBeReplaced()
    {
        $host = $this->host();
        $newHost = IcingaHost::create(
            array('display_name' => 'Replaced display')
        );

        $this->assertEquals(
            count($host->vars()),
            4
        );
        $this->assertEquals(
            $host->address,
            '127.0.0.127'
        );

        $host->replaceWith($newHost);
        $this->assertEquals(
            $host->display_name,
            'Replaced display'
        );
        $this->assertEquals(
            $host->address,
            null
        );

        $this->assertEquals(
            count($host->vars()),
            0
        );
    }

    public function testCanBeMerged()
    {
        $host = $this->host();
        $newHost = IcingaHost::create(
            array('display_name' => 'Replaced display')
        );

        $this->assertEquals(
            count($host->vars()),
            4
        );
        $this->assertEquals(
            $host->address,
            '127.0.0.127'
        );

        $host->merge($newHost);
        $this->assertEquals(
            $host->display_name,
            'Replaced display'
        );
        $this->assertEquals(
            $host->address,
            '127.0.0.127'
        );
        $this->assertEquals(
            count($host->vars()),
            4
        );
    }

    public function testDistinctCustomVarsCanBeSetWithoutSideEffects()
    {
        $host = $this->host();
        $host->set('vars.test2', 18);
        $this->assertEquals(
            $host->vars()->test1->getValue(),
            'string'
        );
        $this->assertEquals(
            $host->vars()->test2->getValue(),
            18
        );
        $this->assertEquals(
            $host->vars()->test3->getValue(),
            false
        );
    }

    public function testVarsArePersisted()
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();
        $this->host()->store($db);
        $host = IcingaHost::load($this->testHostName, $db);
        $this->assertEquals(
            $host->vars()->test1->getValue(),
            'string'
        );
        $this->assertEquals(
            $host->vars()->test2->getValue(),
            17
        );
        $this->assertEquals(
            $host->vars()->test3->getValue(),
            false
        );
        $this->assertEquals(
            $host->vars()->test4->getValue(),
            (object) array(
                'this' => 'is',
                'a' => array(
                    'dict',
                    'ionary'
                )
            )
        );
    }

    public function testRendersCorrectly()
    {
        $this->assertEquals(
            (string) $this->host(),
            $this->loadRendered('host1')
        );
    }

    public function testGivesPlainObjectWithInvalidUnresolvedDependencies()
    {
        $props = $this->getDummyRelatedProperties();

        $host = $this->host();
        foreach ($props as $k => $v) {
            $host->$k = $v;
        }

        $plain = $host->toPlainObject();
        foreach ($props as $k => $v) {
            $this->assertEquals($plain->$k, $v);
        }
    }

    public function testCorrectlyStoresLazyRelations()
    {
        if ($this->skipForMissingDb()) {
            return;
        }
        $db = $this->getDb();
        $host = $this->host();
        $host->zone = '___TEST___zone';
        $this->assertEquals(
            '___TEST___zone',
            $host->zone
        );

        $zone = $this->newObject('zone', '___TEST___zone');
        $zone->store($db);

        $host->store($db);
        $host->delete();
        $zone->delete();
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
        $host = $this->host();
        $host->zone = '___TEST___zone';
        $host->store($db);
    }

    public function testHandlesUnmodifiedProperties()
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();
        $host = $this->host();
        $host->store($db);

        $parent = $this->newObject('host', '___TEST___parent');
        $parent->store($db);
        $host->imports = '___TEST___parent';

        $host->store($db);

        $plain = $host->getPlainUnmodifiedObject();
        $this->assertEquals(
            'string',
            $plain->vars->test1
        );
        $host->vars()->set('test1', 'nada');

        $host->store();

        $plain = $host->getPlainUnmodifiedObject();
        $this->assertEquals(
            'nada',
            $plain->vars->test1
        );

        $host->vars()->set('test1', 'string');
        $plain = $host->getPlainUnmodifiedObject();
        $this->assertEquals(
            'nada',
            $plain->vars->test1
        );

        $plain = $host->getPlainUnmodifiedObject();
        $test = IcingaHost::create((array) $plain);

        $this->assertEquals(
            $this->loadRendered('host3'),
            (string) $test
        );

        $host->delete();
        $parent->delete();
    }

    public function testRendersWithInvalidUnresolvedDependencies()
    {
        $newHost = $this->host();
        $newHost->zone             = 'invalid';
        $newHost->check_command    = 'unknown';
        $newHost->event_command    = 'What event?';
        $newHost->check_period     = 'Not time is a good time @ nite';
        $newHost->command_endpoint = 'nirvana';

        $this->assertEquals(
            (string) $newHost,
            $this->loadRendered('host2')
        );
    }

    /**
     * @expectedException \Icinga\Exception\NotFoundError
     */
    public function testFailsToStoreWithInvalidUnresolvedDependencies()
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $host = $this->host();
        $host->zone = 'invalid';
        $host->store($this->getDb());
    }

    public function testRendersToTheCorrectZone()
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();
        $host = $this->host()->setConnection($db);

        $config = new IcingaConfig($db);
        $host->renderToConfig($config);
        $this->assertEquals(
            array('zones.d/master.conf'),
            $config->getFileNames()
        );

        $zone = $this->newObject('zone', '___TEST___zone');
        $zone->store($db);

        $config = new IcingaConfig($db);
        $host->zone = '___TEST___zone';
        $host->renderToConfig($config);
        $this->assertEquals(
            array('zones.d/___TEST___zone.conf'), 
            $config->getFileNames()
        );

        $host->has_agent = true;
        $host->master_should_connect = true;
        $host->accept_config = true;

        $config = new IcingaConfig($db);
        $host->renderToConfig($config);
        $this->assertEquals(
            array('zones.d/___TEST___zone.conf'), 
            $config->getFileNames()
        );

        $host->object_type = 'template';
        $host->zone_id = null;

        $config = new IcingaConfig($db);
        $host->renderToConfig($config);
        $this->assertEquals(
            array('zones.d/director-global.conf'),
            $config->getFileNames()
        );

    }

    protected function getDummyRelatedProperties()
    {
        return array(
            'zone'             => 'invalid',
            'check_command'    => 'unknown',
            'event_command'    => 'What event?',
            'check_period'     => 'Not time is a good time @ nite',
            'command_endpoint' => 'nirvana',
        );
    }

    protected function host()
    {
        return IcingaHost::create(array(
            'object_name'  => $this->testHostName,
            'object_type'  => 'object',
            'address'      => '127.0.0.127',
            'display_name' => 'Whatever',
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
            $kill = array($this->testHostName, '___TEST___parent');
            foreach ($kill as $name) {
                if (IcingaHost::exists($name, $db)) {
                    IcingaHost::load($name, $db)->delete();
                }
            }

            $kill = array('___TEST___zone');
            foreach ($kill as $name) {
                if (IcingaZone::exists($name, $db)) {
                    IcingaZone::load($name, $db)->delete();
                }
            }
        }
    }
}
