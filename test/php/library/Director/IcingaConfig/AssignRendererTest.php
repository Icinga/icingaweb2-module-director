<?php

namespace Tests\Icinga\Modules\Director;

use Icinga\Data\Filter\Filter;
use Icinga\Module\Director\IcingaConfig\AssignRenderer;
use Icinga\Module\Director\Test\BaseTestCase;

class AssignRendererTest extends BaseTestCase
{
    public function testWhetherACombinedFilterRendersCorrectly()
    {
        $string = 'host.name="*internal"|(service.vars.priority<2'
            . '&host.vars.is_clustered=true)';

        $expected = 'assign where match("*internal", host.name) ||'
            . ' (service.vars.priority < 2 && host.vars.is_clustered == true)';

        $filter = Filter::fromQueryString($string);
        $renderer = AssignRenderer::forFilter($filter);

        $this->assertEquals($expected, $renderer->renderAssign());
    }
}
