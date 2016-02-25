<?php

namespace Tests\Icinga\Modules\Director\Objects;

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
            3
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
            3
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
            3
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
            )
        ));
    }
}
