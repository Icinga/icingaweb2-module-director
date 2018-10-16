<?php

namespace Tests\Icinga\Module\Director\Objects;

use Icinga\Data\Filter\Filter;
use Icinga\Module\Director\Objects\HostApplyMatches;
use Icinga\Module\Director\Objects\IcingaHost;
use Icinga\Module\Director\Test\BaseTestCase;

class HostApplyMatchesTest extends BaseTestCase
{
    public function testExactMatches()
    {
        $matcher = HostApplyMatches::prepare($this->sampleHost());
        $this->assertTrue(
            $matcher->matchesFilter(
                Filter::fromQueryString('host.name=%22aha%22')
            )
        );
        $this->assertFalse(
            $matcher->matchesFilter(
                Filter::fromQueryString('host.name=%22ahaa%22')
            )
        );
    }

    public function testWildcardMatches()
    {
        $matcher = HostApplyMatches::prepare($this->sampleHost());
        $this->assertTrue(
            $matcher->matchesFilter(
                Filter::fromQueryString('host.name=%22ah*%22')
            )
        );
        $this->assertTrue(
            $matcher->matchesFilter(
                Filter::fromQueryString('host.name=%22*h*%22')
            )
        );
        $this->assertFalse(
            $matcher->matchesFilter(
                Filter::fromQueryString('host.name=%22*g*%22')
            )
        );
    }

    public function testStringVariableMatches()
    {
        $matcher = HostApplyMatches::prepare($this->sampleHost());
        $this->assertTrue(
            $matcher->matchesFilter(
                Filter::fromQueryString('host.vars.location=%22*urem*%22')
            )
        );
        $this->assertTrue(
            $matcher->matchesFilter(
                Filter::fromQueryString('host.vars.location=%22Nuremberg%22')
            )
        );
        $this->assertFalse(
            $matcher->matchesFilter(
                Filter::fromQueryString('host.vars.location=%22Nurembergg%22')
            )
        );
    }

    public function testArrayVariableMatches()
    {
        $matcher = HostApplyMatches::prepare($this->sampleHost());
        $this->assertTrue(
            $matcher->matchesFilter(
                Filter::fromQueryString('%22Amazing%22=host.vars.tags')
            )
        );
        $this->assertFalse(
            $matcher->matchesFilter(
                Filter::fromQueryString('%22Amazingg%22=host.vars.tags')
            )
        );
    }

    public function testMatchesIsSet()
    {
        $matcher = HostApplyMatches::prepare($this->sampleHost());
        $this->assertTrue(
            $matcher->matchesFilter(
                Filter::fromQueryString('host.vars.tags')
            )
        );
        $this->assertTrue(
            $matcher->matchesFilter(
                Filter::fromQueryString('host.vars.location=*&host.name')
            )
        );
        $this->assertTrue(
            $matcher->matchesFilter(
                Filter::fromQueryString('host.name')
            )
        );
        $this->assertTrue(
            $matcher->matchesFilter(
                Filter::fromQueryString('host.vars.trueVar')
            )
        );
        $this->assertFalse(
            $matcher->matchesFilter(
                Filter::fromQueryString('host.vars.falseVar')
            )
        );
        $this->assertFalse(
            $matcher->matchesFilter(
                Filter::fromQueryString('!host.name')
            )
        );
    }

    protected function sampleHost()
    {
        return IcingaHost::create([
            'object_type' => 'object',
            'object_name' => 'aha',
            'vars' => [
                'location' => 'Nuremberg',
                'tags'     => ['Special', 'Amazing'],
                'trueVar'  => true,
                'falseVar' => false,
            ]
        ], $this->getDb());
    }
}
