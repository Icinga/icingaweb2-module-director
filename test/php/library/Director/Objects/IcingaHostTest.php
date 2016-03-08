<?php

namespace Tests\Icinga\Module\Director\Objects;

use Icinga\Module\Director\Objects\IcingaHost;
use Icinga\Module\Director\Test\BaseTestCase;

class IcingaHostTest extends BaseTestCase
{
    protected $testHostName = '___TEST___host';

    public function testWhetherHostPropertiesCanBeSet()
    {
        $host = $this->host();
        $host->display_name = 'Something else';
        $this->assertEquals(
            $host->display_name,
            'Something else'
        );
    }

    public function testWhetherHostsCanBeReplaced()
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

    public function testWhetherHostsCanBeMerged()
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

    public function testWhetherDistinctCustomVarsCanBeSetWithoutSideEffects()
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

    public function testWhetherHostVarsArePersisted()
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

    public function testWhetherAHostRendersCorrectly()
    {
        $this->assertEquals(
            (string) $this->host(),
            $this->loadRendered('host1')
        );
    }

    public function testGivesPlainObjectWithInvalidUnresolvedDependencies()
    {
        $props = array(
            'zone'             => 'invalid',
            'check_command'    => 'unknown',
            'event_command'    => 'What event?',
            'check_period'     => 'Not time is a good time @ nite',
            'command_endpoint' => 'nirvana',
        );

        $host = $this->host();
        foreach ($props as $k => $v) {
            $host->$k = $v;
        }

        $plain = $host->toPlainObject();
        foreach ($props as $k => $v) {
            $this->assertEquals($plain->$k, $v);
        }
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
            if (IcingaHost::exists($this->testHostName, $db)) {
                IcingaHost::load($this->testHostName, $db)->delete();
            }
        }
    }
}
