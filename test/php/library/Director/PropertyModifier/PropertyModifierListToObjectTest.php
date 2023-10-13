<?php

namespace Tests\Icinga\Module\Director\Objects;

use Icinga\Module\Director\PropertyModifier\PropertyModifierListToObject;
use Icinga\Module\Director\Test\BaseTestCase;

class PropertyModifierListToObjectTest extends BaseTestCase
{
    public function testConvertsAListOfArrays()
    {
        $this->assertEquals(
            $this->getOutput(),
            $this->getNewModifier()->transform($this->getInputArrays())
        );
    }

    public function testConvertsAListOfObjects()
    {
        $this->assertEquals(
            $this->getOutput(),
            $this->getNewModifier()->transform($this->getInputObjects())
        );
    }

    public function testFailsOnMissingKey()
    {
        $this->expectException(\InvalidArgumentException::class);

        $input = $this->getInputArrays();
        unset($input[0]['name']);
        $this->getNewModifier()->transform($input);
    }

    public function testFailsWithDuplicateRows()
    {
        $this->expectException(\InvalidArgumentException::class);

        $input = $this->getInputArrays();
        $input[1]['name'] = 'row1';
        $this->getNewModifier()->transform($input);
    }

    public function testKeepsFirstRowOnDuplicate()
    {
        $input = $this->getInputArrays();
        $input[1]['name'] = 'row1';
        $modifier = $this->getNewModifier()->setSetting('on_duplicate', 'keep_first');
        $result = $modifier->transform($input);
        $this->assertEquals(
            (object) ['some' => 'property'],
            $result->row1->props
        );
    }

    public function testKeepsLastRowOnDuplicate()
    {
        $input = $this->getInputArrays();
        $input[1]['name'] = 'row1';
        $modifier = $this->getNewModifier()->setSetting('on_duplicate', 'keep_last');
        $result = $modifier->transform($input);
        $this->assertEquals(
            (object) ['other' => 'property'],
            $result->row1->props
        );
    }

    protected function getNewModifier()
    {
        $modifier = new PropertyModifierListToObject();
        $modifier->setSettings([
            'key_property' => 'name',
            'on_duplicate' => 'fail'
        ]);

        return $modifier;
    }

    protected function getInputArrays()
    {
        return [
            ['name' => 'row1', 'props' => (object) ['some' => 'property']],
            ['name' => 'row2', 'props' => (object) ['other' => 'property']],
        ];
    }

    protected function getInputObjects()
    {
        return [
            (object) ['name' => 'row1', 'props' => (object) ['some' => 'property']],
            (object) ['name' => 'row2', 'props' => (object) ['other' => 'property']],
        ];
    }

    protected function getOutput()
    {
        return (object) [
            'row1' => (object) ['name' => 'row1', 'props' => (object) ['some' => 'property']],
            'row2' => (object) ['name' => 'row2', 'props' => (object) ['other' => 'property']],
        ];
    }
}
