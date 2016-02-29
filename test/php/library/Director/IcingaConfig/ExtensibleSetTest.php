<?php

namespace Tests\Icinga\Module\Director\IcingaConfig;

use Icinga\Module\Director\IcingaConfig\ExtensibleSet;
use Icinga\Module\Director\Objects\IcingaUser;
use Icinga\Module\Director\Test\BaseTestCase;

class ExtensibleSetTest extends BaseTestCase
{
    public function testNoValuesResultInEmptySet()
    {
        $set = new ExtensibleSet();

        $this->assertEquals(
            array(),
            $set->getResolvedValues()
        );
    }

    public function testValuesPassedToConstructorAreAccepted()
    {
        $values = array('Val1', 'Val2', 'Val4');
        $set = new ExtensibleSet($values);

        $this->assertEquals(
            $values,
            $set->getResolvedValues()
        );
    }

    public function testConstructorAcceptsSingleValues()
    {
        $set = new ExtensibleSet('Bla');

        $this->assertEquals(
            array('Bla'),
            $set->getResolvedValues()
        );
    }

    public function testSingleValuesCanBeBlacklisted()
    {
        $values = array('Val1', 'Val2', 'Val4');
        $set = new ExtensibleSet($values);
        $set->blacklist('Val2');

        $this->assertEquals(
            array('Val1', 'Val4'),
            $set->getResolvedValues()
        );
    }

    public function testMultipleValuesCanBeBlacklisted()
    {
        $values = array('Val1', 'Val2', 'Val4');
        $set = new ExtensibleSet($values);
        $set->blacklist(array('Val4', 'Val1'));

        $this->assertEquals(
            array('Val2'),
            $set->getResolvedValues()
        );
    }

    public function testSimpleInheritanceWorksFine()
    {
        $values = array('Val1', 'Val2', 'Val4');
        $parent = new ExtensibleSet($values);
        $child = new ExtensibleSet();
        $child->inheritFrom($parent);

        $this->assertEquals(
            $values,
            $child->getResolvedValues()
        );
    }

    public function testWeCanInheritFromMultipleParents()
    {
        $p1set = array('p1a', 'p1c');
        $p2set = array('p2a', 'p2d');
        $parent1 = new ExtensibleSet($p1set);
        $parent2 = new ExtensibleSet($p2set);
        $child = new ExtensibleSet();
        $child->inheritFrom($parent1)->inheritFrom($parent2);

        $this->assertEquals(
            $p2set,
            $child->getResolvedValues()
        );
    }

    public function testOwnValuesOverrideParents()
    {
        $cset = array('p1a', 'p1c');
        $pset = array('p2a', 'p2d');
        $child = new ExtensibleSet($cset);
        $parent = new ExtensibleSet($pset);
        $child->inheritFrom($parent);

        $this->assertEquals(
            $cset,
            $child->getResolvedValues()
        );
    }

    public function testInheritedValuesCanBeBlacklisted()
    {
        $child = new ExtensibleSet();
        $child->blacklist('p2');

        $pset = array('p1', 'p2', 'p3');
        $parent = new ExtensibleSet($pset);

        $child->inheritFrom($parent);
        $child->blacklist(array('not', 'yet', 'p1'));

        $this->assertEquals(
            array('p3'),
            $child->getResolvedValues()
        );

        $child->blacklist(array('p3'));
        $this->assertEquals(
            array(),
            $child->getResolvedValues()
        );
    }

    public function testInheritedValuesCanBeExtended()
    {
        $pset = array('p1', 'p2', 'p3');

        $child = new ExtensibleSet();
        $child->extend('p5');

        $parent = new ExtensibleSet($pset);
        $child->inheritFrom($parent);

        $this->assertEquals(
            array('p1', 'p2', 'p3', 'p5'),
            $child->getResolvedValues()
        );
    }

    public function testCombinedDefinitionRendersCorrectly()
    {
        $set = new ExtensibleSet(array('Pre', 'Def', 'Ined'));
        $set->blacklist(array('And', 'Not', 'Those'));
        $set->extend('PlusThis');

        $out = '    key_name = [ Pre, Def, Ined ]' . "\n"
             . '    key_name += [ PlusThis ]' . "\n"
             . '    key_name -= [ And, Not, Those ]' . "\n";

        $this->assertEquals(
            $out,
            $set->renderAs('key_name')
        );
    }
}
