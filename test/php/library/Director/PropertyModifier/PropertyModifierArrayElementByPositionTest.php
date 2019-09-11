<?php

namespace Tests\Icinga\Module\Director\Objects;

use Icinga\Module\Director\PropertyModifier\PropertyModifierArrayElementByPosition;
use Icinga\Module\Director\Test\BaseTestCase;

class PropertyModifierArrayElementByPositionTest extends BaseTestCase
{
    /*
     * Allowed settings:
     *
     * position_type: first, last, fixed
     * position
     * when_missing: fail, null
     */

    public function testGivesFirstElementOfArray()
    {
        $this->assertEquals(
            'one',
            $this->buildModifier('first')->transform(['one', 'two', 'three'])
        );
    }

    public function testGivesFirstElementOfObject()
    {
        $this->assertEquals(
            'one',
            $this->buildModifier('first')->transform((object) ['one', 'two', 'three'])
        );
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testGettingFirstFailsForEmptyArray()
    {
        $this->buildModifier('first')->transform([]);
    }

    public function testGettingFirstGivesNullForEmptyArray()
    {
        $this->assertNull($this->buildModifier('first', null, 'null')->transform([]));
    }

    public function testGivesLastElementOfArray()
    {
        $this->assertEquals(
            'three',
            $this->buildModifier('last')->transform(['one', 'two', 'three'])
        );
    }

    public function testGivesLastElementOfObject()
    {
        $this->assertEquals(
            'three',
            $this->buildModifier('last')->transform((object) ['one', 'two', 'three'])
        );
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testGettingLastFailsForEmptyArray()
    {
        $this->buildModifier('last')->transform([]);
    }

    public function testGettingLastGivesNullForEmptyArray()
    {
        $this->assertNull($this->buildModifier('last', null, 'null')->transform([]));
    }

    public function testGivesSpecificElementOfArray()
    {
        $this->assertEquals(
            'two',
            $this->buildModifier('fixed', '1')->transform(['one', 'two', 'three'])
        );
    }

    public function testGivesSpecificElementOfObject()
    {
        $this->assertEquals(
            'two',
            $this->buildModifier('fixed', 1)->transform((object) ['one', 'two', 'three'])
        );
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testGettingSpecificFailsForEmptyArray()
    {
        $this->buildModifier('fixed', 1)->transform([]);
    }

    public function testGettingSpecificGivesNullForEmptyArray()
    {
        $this->assertNull($this->buildModifier('fixed', 1, 'null')->transform([]));
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testGettingSpecificFailsForMissingValue()
    {
        $this->buildModifier('fixed', 3)->transform(['one', 'two', 'three']);
    }

    public function testGettingSpecificGivesNullForMissingValue()
    {
        $this->assertNull($this->buildModifier('fixed', 3, 'null')->transform(['one', 'two', 'three']));
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testFailsForStrings()
    {
        $this->buildModifier('first')->transform('string');
    }

    public function testAnnouncesArraySupport()
    {
        $modifier = new PropertyModifierArrayElementByPosition();
        $this->assertTrue($modifier->hasArraySupport());
    }

    protected function buildModifier($type, $position = null, $whenMissing = 'fail')
    {
        $modifier = new PropertyModifierArrayElementByPosition();
        $modifier->setSettings([
            'position_type' => $type,
            'position'      => $position,
            'when_missing'  => $whenMissing,
        ]);

        return $modifier;
    }
}
