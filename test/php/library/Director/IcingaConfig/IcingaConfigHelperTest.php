<?php

namespace Tests\Icinga\Module\Director\IcingaConfig;

use Icinga\Module\Director\IcingaConfig\IcingaConfigHelper as c;
use Icinga\Module\Director\Test\BaseTestCase;

class IcingaConfigHelperTest extends BaseTestCase
{
    public function testWhetherIntervalStringIsCorrectlyParsed()
    {
        $this->assertEquals(c::parseInterval('0'), 0);
        $this->assertEquals(c::parseInterval('0s'), 0);
        $this->assertEquals(c::parseInterval('10'), 10);
        $this->assertEquals(c::parseInterval('70s'), 70);
        $this->assertEquals(c::parseInterval('5m 10s'), 310);
        $this->assertEquals(c::parseInterval('5m 60s'), 360);
        $this->assertEquals(c::parseInterval('1h 5m 60s'), 3960);
    }

    /**
     * @expectedException \Icinga\Exception\ProgrammingError
     */
    public function testWhetherInvalidIntervalStringRaisesException()
    {
        c::parseInterval('1h 5m 60x');
    }

    public function testWhetherAnEmptyValueGivesNull()
    {
        $this->assertNull(c::parseInterval(''));
        $this->assertNull(c::parseInterval(null));
    }

    public function testWhetherIntervalStringIsCorrectlyRendered()
    {
        $this->assertEquals(c::renderInterval(10), '10s');
        $this->assertEquals(c::renderInterval(60), '1m');
        $this->assertEquals(c::renderInterval(121), '121s');
        $this->assertEquals(c::renderInterval(3600), '1h');
        $this->assertEquals(c::renderInterval(86400), '1d');
        $this->assertEquals(c::renderInterval(86459), '86459s');
    }
}
