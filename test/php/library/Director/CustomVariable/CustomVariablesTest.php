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
        $expected = $this->indentVarsList([
            'vars["a-0"] = "special"',
            'vars.aBc = "normal"',
            'vars.bla = "da"'
        ]);
        $this->assertEquals($expected, $vars->toConfigString());
    }

    public function testVarsCanBeUnsetAndSetAgain()
    {
        $vars = $this->newVars();
        $vars->one = 'two';
        unset($vars->one);
        $vars->one = 'three';

        $res = [];
        foreach ($vars as $k => $v) {
            $res[$k] = $v->getValue();
        }

        $this->assertEquals(['one' => 'three'], $res);
    }

    public function testNumericKeysAreRenderedWithArraySyntax()
    {
        $vars = $this->newVars();
        $vars->{'1'} = 1;
        $expected = $this->indentVarsList([
            'vars["1"] = 1'
        ]);

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
        $expected = $this->indentVarsList([
            'vars.abc = "$val$"',
            'vars.bla = "da"'
        ]);
        $this->assertEquals($expected, $vars->toConfigString(true));
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
