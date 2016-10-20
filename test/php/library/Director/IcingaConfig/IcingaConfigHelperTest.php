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

    public function testCorrectlyIdentifiesReservedWords()
    {
        $this->assertTrue(c::isReserved('include'), 'include is a reserved word');
        $this->assertFalse(c::isReserved(0), '(int) 0 is not a reserved word');
        $this->assertFalse(c::isReserved(1), '(int) 1 is not a reserved word');
        $this->assertFalse(c::isReserved(true), '(boolean) true is not a reserved word');
        $this->assertTrue(c::isReserved('true'), '(string) true is a reserved word');
    }

    public function testWhetherDictionaryRendersCorrectly()
    {
        $dict = (object) array(
            'key1'     => 'bla',
            'include'  => 'reserved',
            'spe cial' => 'value',
            '0'        => 'numeric',
        );
        $this->assertEquals(
            c::renderDictionary($dict),
            rtrim($this->loadRendered('dict1'))
        );
    }

    protected function loadRendered($name)
    {
        return file_get_contents(__DIR__ . '/rendered/' . $name . '.out');
    }

    public function testRenderStringIsCorrectlyRendered()
    {
        $this->assertEquals(c::renderString('val1\\\val2'), '"val1\\\\\\\\val2"');
        $this->assertEquals(c::renderString('"val1"'), '"\"val1\""');
        $this->assertEquals(c::renderString('\$val\$'), '"\\\\$val\\\\$"');
        $this->assertEquals(c::renderString('\t'), '"\\\\t"');
        $this->assertEquals(c::renderString('\r'), '"\\\\r"');
        $this->assertEquals(c::renderString('\n'), '"\\\\n"');
        $this->assertEquals(c::renderString('\f'), '"\\\\f"');
    }

    public function testRenderStringWithVariables()
    {
        $this->assertEquals(c::renderString('Before $var$', true), '"Before " + var');
        $this->assertEquals(c::renderString('$var$ After', true), 'var + " After"');
        $this->assertEquals(c::renderString('$var$', true), 'var');
        $this->assertEquals(c::renderString('$$var$$', true), '"$$var$$"');
        $this->assertEquals(c::renderString('Before $$var$$ After', true), '"Before $$var$$ After"');
        $this->assertEquals(
            c::renderString('Before $name$ $name$ After', true),
            '"Before " + name + " " + name + " After"');
        $this->assertEquals(
            c::renderString('Before $var1$ $var2$ After', true),
            '"Before " + var1 + " " + var2 + " After"');
        $this->assertEquals(c::renderString('$host.vars.custom$', true), 'host.vars.custom');
        $this->assertEquals(c::renderString('$var"$', true), '"$var\"$"');
        $this->assertEquals(
            c::renderString('\tI am\rrendering\nproperly\fand I $support$ "multiple" $variables$\$', true),
            '"\\\\tI am\\\\rrendering\\\\nproperly\\\\fand I " + support + " \"multiple\" " + variables + "\\\\$"');
    }
}
