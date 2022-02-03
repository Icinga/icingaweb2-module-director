<?php

namespace Tests\Icinga\Module\Director\Application;

use Icinga\Data\Filter\Filter;
use Icinga\Module\Director\Test\BaseTestCase;

class FiltersWorkAsExpectedTest extends BaseTestCase
{
    public function testBasics()
    {
        $filter = Filter::fromQueryString('a');
        $this->assertTrue($filter->matches((object) ['a' => '1']), '1 is not true');
    }
}
