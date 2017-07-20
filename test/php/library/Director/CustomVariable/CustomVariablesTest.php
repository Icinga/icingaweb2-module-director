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

    public function testNumericKeysAreRenderedWithArraySyntax()
    {
        $vars = $this->newVars();
        $vars->{'1'} = 1;
        $expected = $this->indentVarsList(array(
            'vars["1"] = 1'
        ));

        $this->assertEquals(
            $expected,
            $vars->toConfigString(true)
        );
    }

    public function testVariablesToExpression()
    {
        $vars = $this->newVars();
        $vars->bla = 'da';
        $vars->abc = '$val$';
        $expected = $this->indentVarsList(array(
            'vars.abc = val',
            'vars.bla = "da"'
        ));
        $this->assertEquals(
            $vars->toConfigString(true),
            $expected
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
