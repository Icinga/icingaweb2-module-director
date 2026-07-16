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

    public function testWhetherInvalidIntervalStringRaisesException()
    {
        $this->expectException(\InvalidArgumentException::class);

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
        $dict = (object) [
            'address' => '192.0.2.10',
            'include' => 'reserved',
            'on call' => 'contact',
            '0'       => 'numeric',
        ];
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
        $this->assertEquals(c::renderString('C:\Program Files\NSClient++'), '"C:\\\\Program Files\\\\NSClient++"');
        $this->assertEquals(c::renderString('"check_disk"'), '"\"check_disk\""');
        $this->assertEquals(c::renderString('\$ORACLE_SID\$'), '"\\\\$ORACLE_SID\\\\$"');
        $this->assertEquals(c::renderString('\t'), '"\\\\t"');
        $this->assertEquals(c::renderString('\r'), '"\\\\r"');
        $this->assertEquals(c::renderString('\n'), '"\\\\n"');
        $this->assertEquals(c::renderString('\f'), '"\\\\f"');
    }

    public function testMacrosAreDetected()
    {
        $this->assertFalse(c::stringHasMacro('$$vars$'));
        $this->assertFalse(c::stringHasMacro('$$'));
        $this->assertTrue(c::stringHasMacro('$vars$$'));
        $this->assertTrue(c::stringHasMacro('$address$$vars.nested.name$$vars$ is here'));
        $this->assertTrue(c::stringHasMacro('some $vars.nested.name$ is here'));
        $this->assertTrue(c::stringHasMacro('some $vars.nested.name$$vars.even.more$'));
        $this->assertTrue(c::stringHasMacro('$vars.nested.name$$ip$$$$sid$'));
        $this->assertTrue(c::stringHasMacro('MSSQL$$$config$'));
        $this->assertTrue(c::stringHasMacro('MSSQL$$$config$', 'config'));
        $this->assertTrue(c::stringHasMacro('MSSQL$$$linux$ and $config$', 'config'));
        $this->assertFalse(c::stringHasMacro('MSSQL$$$linux$config$ and $$', 'config'));
        $this->assertFalse(c::stringHasMacro('MSSQL$$$linux$ and $$config$', 'config'));
        $this->assertFalse(c::stringHasMacro('MSSQL$$$config$', 'conf'));
    }

    public function testRenderStringWithVariables()
    {
        $this->assertEquals('"Before " + address', c::renderStringWithVariables('Before $address$'));
        $this->assertEquals(c::renderStringWithVariables('$address$ After'), 'address + " After"');
        $this->assertEquals(c::renderStringWithVariables('$address$'), 'address');
        $this->assertEquals(c::renderStringWithVariables('$$address$$'), '"$$address$$"');
        $this->assertEquals(c::renderStringWithVariables('Before $$address$$ After'), '"Before $$address$$ After"');
        $this->assertEquals(
            '"Before " + display_name + " " + check_command + " After"',
            c::renderStringWithVariables('Before $display_name$ $check_command$ After')
        );
    }

    public function testRenderStringWithVariablesX()
    {
        $this->assertEquals(
            '"Before " + address + " " + port + " After"',
            c::renderStringWithVariables('Before $address$ $port$ After')
        );
        $this->assertEquals(
            'host.vars.custom',
            c::renderStringWithVariables('$host.vars.custom$')
        );
        $this->assertEquals('"$address\"$"', c::renderStringWithVariables('$address"$'));
        $this->assertEquals(
            '"\\\\tCPU load\\\\ris\\\\nabove\\\\fwarning on " + address + " \"threshold\" " + display_name + "\\\\$"',
            c::renderStringWithVariables('\tCPU load\ris\nabove\fwarning on $address$ "threshold" $display_name$\$')
        );
    }

    public function testIsValidMacroNameWithNoWhitelist()
    {
        // Valid names: letter/underscore start, letters/digits/underscores/dots, no trailing dot
        $this->assertTrue(c::isValidMacroName('host.vars.custom'));
        $this->assertTrue(c::isValidMacroName('value.path'));
        $this->assertTrue(c::isValidMacroName('check_interval'));
        $this->assertTrue(c::isValidMacroName('ab'));

        // Single character is invalid: the pattern requires at least 2 characters
        $this->assertFalse(c::isValidMacroName('a'));

        // Trailing dot is explicitly rejected
        $this->assertFalse(c::isValidMacroName('value.'));
        $this->assertFalse(c::isValidMacroName('host.'));

        // Starts with a digit: not matched by [A-z_]
        $this->assertFalse(c::isValidMacroName('1invalid'));

        // Empty string
        $this->assertFalse(c::isValidMacroName(''));
    }

    public function testIsValidMacroNameExactWhitelistMatch()
    {
        $this->assertTrue(c::isValidMacroName('value.path', ['value.path']));
        $this->assertTrue(c::isValidMacroName('value.mount_point', ['value.path', 'value.mount_point']));
    }

    public function testIsValidMacroNameWildcardWhitelistMatch()
    {
        $this->assertTrue(c::isValidMacroName('value.mount_point', ['value.*']));
        $this->assertTrue(c::isValidMacroName('value.warn', ['value.*']));
        $this->assertTrue(c::isValidMacroName('host.address', ['host.*', 'value.*']));
    }

    public function testIsValidMacroNameWhitelistNoMatch()
    {
        // Name not in whitelist and not matching any wildcard returns false
        $this->assertFalse(c::isValidMacroName('host.vars.custom', ['value.*']));
        $this->assertFalse(c::isValidMacroName('host.vars.custom', ['check_command']));
    }

    public function testIsValidMacroNameEmptyWhitelistReturnsFalse()
    {
        // When a non-null whitelist is provided, only whitelist matches count —
        // an empty whitelist means nothing is permitted
        $this->assertFalse(c::isValidMacroName('host.vars.custom', []));
        $this->assertFalse(c::isValidMacroName('value.path', []));
    }

    public function testIsValidMacroNameWhitelistOverridesPatternCheck()
    {
        // A name that does not match the base macro pattern is still valid when
        // explicitly listed in the whitelist
        $this->assertTrue(c::isValidMacroName('a', ['a']));
        $this->assertTrue(c::isValidMacroName('value.', ['value.']));
    }

    public function testIsValidMacroNameWildcardDoesNotBypassSyntaxCheck(): void
    {
        $this->assertFalse(c::isValidMacroName('host.name) { throw "injected"', ['host.*']));
        $this->assertFalse(c::isValidMacroName('value[0 OR 1=1]', ['value[*]']));
        $this->assertFalse(c::isValidMacroName('value[0].sub) { evil', ['value[*].*']));
        $this->assertFalse(c::isValidMacroName('value["on call\\") { evil', ['value[*]']));
    }

    public function testIsValidMacroNameWildcardStillMatchesArrayIndexAndDictionaryForms(): void
    {
        $whiteList = ['value', 'host.*', 'value[*]', 'value[*].*'];

        $this->assertTrue(c::isValidMacroName('host.vars.custom', $whiteList));
        $this->assertTrue(c::isValidMacroName('value[0]', $whiteList));
        $this->assertTrue(c::isValidMacroName('value[12]', $whiteList));
        $this->assertTrue(c::isValidMacroName('value[0].sub_key', $whiteList));
        $this->assertFalse(c::isValidMacroName('value[]', $whiteList));
        $this->assertFalse(c::isValidMacroName('value[abc]', $whiteList));
    }

    public function testIsValidMacroNameWildcardMatchesQuotedDictionaryKeyWithSpace(): void
    {
        $whiteList = ['value', 'host.*', 'value[*]', 'value[*].*'];

        $this->assertTrue(c::isValidMacroName('value["on call contact"]', $whiteList));
        $this->assertTrue(c::isValidMacroName('value["on call contact"].email', $whiteList));
        $this->assertFalse(c::isValidMacroName('value["on call contact]', $whiteList));
    }
}
