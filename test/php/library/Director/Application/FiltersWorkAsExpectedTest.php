<?php

// SPDX-FileCopyrightText: 2022 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

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
