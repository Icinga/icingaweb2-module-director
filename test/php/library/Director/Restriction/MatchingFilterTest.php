<?php

namespace Tests\Icinga\Module\Director\Restriction;

use Icinga\Module\Director\Restriction\MatchingFilter;
use Icinga\Module\Director\Test\BaseTestCase;
use Icinga\User;

class MatchingFilterTest extends BaseTestCase
{
    public function testUserWithNoRestrictionHasNoFilter()
    {
        $user = new User('dummy');
        $this->assertEquals(
            '',
            (string) MatchingFilter::forUser($user, 'some/name', 'prop')
        );
    }

    public function testSimpleRestrictionRendersCorrectly()
    {
        $this->assertEquals(
            'prop = a*',
            (string) MatchingFilter::forPatterns(['a*'], 'prop')
        );
    }

    public function testMultipleRestrictionsAreCombinedWithOr()
    {
        $this->assertEquals(
            'prop = a* | prop = *b',
            (string) MatchingFilter::forPatterns(['a*', '*b'], 'prop')
        );
    }

    public function testUserWithMultipleRestrictionsWorksFine()
    {
        $user = new User('dummy');
        $user->setRestrictions([
            'some/name' => ['a*', '*b'],
            'some/thing' => ['else']
        ]);
        $this->assertEquals(
            'prop = a* | prop = *b',
            (string) MatchingFilter::forUser($user, 'some/name', 'prop')
        );
    }

    public function testSingleRestrictionAllowsForPipes()
    {
        $this->assertEquals(
            'prop = a* | prop = *b',
            (string) MatchingFilter::forPatterns(['a*|*b'], 'prop')
        );
    }
}
