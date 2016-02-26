<?php

namespace Tests\Icinga\Module\Director\IcingaConfig;

use Icinga\Data\Filter\Filter;
use Icinga\Module\Director\IcingaConfig\AssignRenderer;
use Icinga\Module\Director\Test\BaseTestCase;

class AssignRendererTest extends BaseTestCase
{
    public function testWhetherEqualMatchIsCorrectlyRendered()
    {
        $string = 'host.name="localhost"';
        $expected = 'assign where host.name == "localhost"';
        $this->assertEquals(
            $expected,
            $this->renderer($string)->renderAssign()
        );
    }

    public function testWhetherWildcardsRenderAMatchMethod()
    {
        $string = 'host.address="127.0.0.*"';
        $expected = 'assign where match("127.0.0.*", host.address)';
        $this->assertEquals(
            $expected,
            $this->renderer($string)->renderAssign()
        );
    }

    public function testWhetherACombinedFilterRendersCorrectly()
    {
        $string = 'host.name="*internal"|(service.vars.priority<2'
            . '&host.vars.is_clustered=true)';

        $expected = 'assign where match("*internal", host.name) ||'
            . ' (service.vars.priority < 2 && host.vars.is_clustered == true)';

        $this->assertEquals(
            $expected,
            $this->renderer($string)->renderAssign()
        );
    }

    protected function renderer($string)
    {
        return AssignRenderer::forFilter(Filter::fromQueryString($string));
    }
}
