<?php

// SPDX-FileCopyrightText: 2026 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Tests\Icinga\Module\Director\Restriction;

use Icinga\Data\Filter\FilterExpression;
use Icinga\Data\Filter\FilterOr;
use Icinga\Exception\ConfigurationError;
use Icinga\Module\Director\Restriction\HostFilter;
use Icinga\Module\Director\Test\BaseTestCase;

class HostFilterTest extends BaseTestCase
{
    public function testNoRestrictionsGiveNoFilter(): void
    {
        $this->assertNull(HostFilter::fromRestrictions([]));
    }

    public function testEmptyRestrictionsAreIgnored(): void
    {
        $this->assertNull(HostFilter::fromRestrictions(['', '  ']));
    }

    public function testSingleRestrictionIsUsedAsItIs(): void
    {
        $this->assertEquals(
            'host.vars.team = Team_A',
            (string) HostFilter::fromRestrictions(['host.vars.team=Team_A'])
        );
    }

    public function testRestrictionsOfMultipleRolesAreCombinedWithOr(): void
    {
        $filter = HostFilter::fromRestrictions(['host.vars.team=Team_A', 'hostgroup.name=linux']);

        $this->assertInstanceOf(FilterOr::class, $filter);
        $this->assertCount(2, $filter->filters());
    }

    public function testAllowedColumnsCanBeCombined(): void
    {
        HostFilter::parse('host.name=web*&(hostgroup.name=linux|!(host.vars.team=Team_A))');
        $this->addToAssertionCount(1);
    }

    public function testUnknownColumnIsRejected(): void
    {
        $this->expectException(ConfigurationError::class);
        HostFilter::parse('host.address=127.0.0.1');
    }

    public function testUnknownColumnIsRejectedWhenNested(): void
    {
        $this->expectException(ConfigurationError::class);
        HostFilter::parse('host.vars.team=Team_A&(hostgroup.name=linux|service.name=ping)');
    }

    public function testVarsColumnNeedsAVariableName(): void
    {
        $this->expectException(ConfigurationError::class);
        HostFilter::parse('host.vars.=Team_A');
    }

    public function testArrayMarkersAreAllowed(): void
    {
        HostFilter::parse('host.vars.team[]=Team_A|host.vars.team[*]=Team_B');
        $this->addToAssertionCount(1);
    }

    public function testHostgroupNameIsPreparedAsHostGroups(): void
    {
        $filter = HostFilter::prepareForMatching(HostFilter::parse('hostgroup.name=linux'));

        $this->assertInstanceOf(FilterExpression::class, $filter);
        $this->assertEquals('host.groups', $filter->getColumn());
        $this->assertEquals('"linux"', $filter->getExpression());
    }

    public function testArrayMarkersAreDroppedForMatching(): void
    {
        $filter = HostFilter::prepareForMatching(
            HostFilter::parse('host.vars.team[]=Team_A|host.vars.team[*]=Team_B')
        );

        $columns = array_map(function (FilterExpression $expression) {
            return $expression->getColumn();
        }, $filter->filters());

        $this->assertEquals(['host.vars.team', 'host.vars.team'], $columns);
    }

    public function testPreparingLeavesTheGivenFilterUntouched(): void
    {
        $filter = HostFilter::parse('hostgroup.name=linux');
        HostFilter::prepareForMatching($filter);

        $this->assertEquals('hostgroup.name', $filter->getColumn());
        $this->assertEquals('linux', $filter->getExpression());
    }
}
