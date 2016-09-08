<?php

namespace Tests\Icinga\Module\Director\CustomVariable;

use Icinga\Module\Director\CustomVariable\CustomVariables;
use Icinga\Module\Director\Test\BaseTestCase;

class CustomVariablesTest extends BaseTestCase
{
    protected $indent = '    ';

    public function testWhetherSpecialKeyNames()
    {
        $vars = $this->newVars();
        $vars->bla = 'da';
        $vars->{'aBc'} = 'normal';
        $vars->{'a-0'} = 'special';
        $expected = $this->indentVarsList(array(
            'vars["a-0"] = "special"',
            'vars.aBc = "normal"',
            'vars.bla = "da"'
        ));
        $this->assertEquals(
            $vars->toConfigString(),
            $expected
        );
    }

    public function testVarsCanBeUnsetAndSetAgain()
    {
        $vars = $this->newVars();
        $vars->one = 'two';
        unset($vars->one);
        $vars->one = 'three';

        $res = array();
        foreach ($vars as $k => $v) {
            $res[$k] = $v->getValue();
        }

        $this->assertEquals(
            array('one' => 'three'),
            $res
        );
    }

    protected function indentVarsList($vars)
    {
        return $this->indent . implode(
            "\n" . $this->indent,
            $vars
        ) . "\n";
    }

    protected function newVars()
    {
        return new CustomVariables();
    }
}
