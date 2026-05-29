<?php

// SPDX-FileCopyrightText: 2026 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Tests\Icinga\Module\Director\CustomVariable;

use Icinga\Module\Director\CustomVariable\CustomVariables;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for CustomVariables rendering across the types used in real datacenter monitoring.
 *
 * Scenario: a host template for Linux servers carrying environment strings, check counts,
 * feature flags, HTTP vhost lists, disk threshold maps, and check-interval overrides.
 */
class CustomVariableDictionaryRenderingTest extends TestCase
{
    protected string $indent = '    ';

    public function testStringRendersSingleLine(): void
    {
        $vars = new CustomVariables();
        $vars->env = 'production';

        $this->assertEquals(
            $this->indent . 'vars.env = "production"' . "\n",
            $vars->toConfigString()
        );
    }

    public function testNumberRendersWithoutQuotes(): void
    {
        $vars = new CustomVariables();
        $vars->max_check_attempts = 3;

        $this->assertEquals(
            $this->indent . 'vars.max_check_attempts = 3' . "\n",
            $vars->toConfigString()
        );
    }

    public function testBooleanRendersAsKeyword(): void
    {
        $vars = new CustomVariables();
        $vars->notifications_enabled = false;

        $this->assertEquals(
            $this->indent . 'vars.notifications_enabled = false' . "\n",
            $vars->toConfigString()
        );
    }

    public function testArrayRendersAsBracketList(): void
    {
        $vars = new CustomVariables();
        $vars->http_vhosts = ['web01.example.com', 'web02.example.com'];

        $this->assertEquals(
            $this->indent . 'vars.http_vhosts = [ "web01.example.com", "web02.example.com" ]' . "\n",
            $vars->toConfigString()
        );
    }

    public function testFixedDictionaryRendersAsBlock(): void
    {
        $vars = new CustomVariables();
        $vars->disk_thresholds = ['warn' => '10%', 'crit' => '5%'];

        // Dictionary keys are sorted; crit before warn
        $expected = $this->indent . 'vars.disk_thresholds = {' . "\n"
            . $this->indent . $this->indent . 'crit = "5%"' . "\n"
            . $this->indent . $this->indent . 'warn = "10%"' . "\n"
            . $this->indent . '}' . "\n";

        $this->assertEquals($expected, $vars->toConfigString());
    }

    public function testDynamicDictionaryRendersWithPlusEquals(): void
    {
        $vars = new CustomVariables();
        $vars->disk_checks = ['warn' => '20%', 'crit' => '10%'];
        $vars->setOverrideKeyName('disk_checks');

        // += operator used when the key matches the override key name
        $expected = $this->indent . 'vars.disk_checks += {' . "\n"
            . $this->indent . $this->indent . 'crit = "10%"' . "\n"
            . $this->indent . $this->indent . 'warn = "20%"' . "\n"
            . $this->indent . '}' . "\n";

        $this->assertEquals($expected, $vars->toConfigString());
    }

    public function testDynamicDictionaryRendersWithEqualsWhenNotOverride(): void
    {
        $vars = new CustomVariables();
        $vars->disk_checks = ['warn' => '20%', 'crit' => '10%'];
        // no setOverrideKeyName call — ordinary assignment

        $expected = $this->indent . 'vars.disk_checks = {' . "\n"
            . $this->indent . $this->indent . 'crit = "10%"' . "\n"
            . $this->indent . $this->indent . 'warn = "20%"' . "\n"
            . $this->indent . '}' . "\n";

        $this->assertEquals($expected, $vars->toConfigString());
    }

    public function testSpecialKeyNameUsesArraySyntax(): void
    {
        $vars = new CustomVariables();
        $vars->{'check-interval'} = 60;

        $this->assertEquals(
            $this->indent . 'vars["check-interval"] = 60' . "\n",
            $vars->toConfigString()
        );
    }

    public function testApplyForWhitelistAllowsValueMacro(): void
    {
        $vars = new CustomVariables();
        $vars->setWhiteList(['value.path']);
        $vars->url = '$value.path$';

        // Whitelisted macro rendered as an unquoted Icinga 2 expression reference
        $this->assertEquals(
            $this->indent . 'vars.url = value.path' . "\n",
            $vars->toConfigString(true)
        );
    }

    public function testApplyForWhitelistStripsUnknownValueMacro(): void
    {
        $vars = new CustomVariables();
        $vars->setWhiteList(['value.path']);
        $vars->url = '$value.not_a_field$';

        // Unknown macro is not in the whitelist: rendered as a quoted string with the
        // macro syntax preserved rather than emitted as an unquoted expression
        $this->assertEquals(
            $this->indent . 'vars.url = "$value.not_a_field$"' . "\n",
            $vars->toConfigString(true)
        );
    }

    public function testDatalistValueRendersAsString(): void
    {
        $vars = new CustomVariables();
        // A var whose value comes from a datalist is stored as a plain string; the
        // datalist constraint is a UI concern only, not reflected in config rendering
        $vars->env = 'production';

        $this->assertEquals(
            $this->indent . 'vars.env = "production"' . "\n",
            $vars->toConfigString()
        );
    }
}
