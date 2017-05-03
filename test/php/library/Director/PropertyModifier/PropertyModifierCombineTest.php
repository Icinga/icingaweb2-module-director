<?php

namespace Tests\Icinga\Module\Director\Objects;

use Icinga\Module\Director\PropertyModifier\PropertyModifierCombine;
use Icinga\Module\Director\Test\BaseTestCase;

class PropertyModifierCombineTest extends BaseTestCase
{
    public function testBuildsTypicalHostServiceCombination()
    {
        $row = (object) array('host' => 'localhost', 'service' => 'ping');
        $modifier = new PropertyModifierCombine();
        $modifier->setSettings(array('pattern' => '${host}!${service}'));

        $this->assertEquals(
            'localhost!ping',
            $modifier->setRow($row)->transform('something')
        );
    }

    public function testDoesNotFailForMissingProperties()
    {
        $row = (object) array('host' => 'localhost');
        $modifier = new PropertyModifierCombine();
        $modifier->setSettings(array('pattern' => '${host}!${service}'));

        $this->assertEquals(
            'localhost!',
            $modifier->setRow($row)->transform('something')
        );
    }

    public function testDoesNotEvaluateVariablesFromDataSource()
    {
        $row = (object) array('host' => '${service}', 'service' => 'ping');
        $modifier = new PropertyModifierCombine();
        $modifier->setSettings(array('pattern' => '${host}!${service}'));

        $this->assertEquals(
            '${service}!ping',
            $modifier->setRow($row)->transform('something')
        );
    }

    public function testRequiresRow()
    {
        $modifier = new PropertyModifierCombine();
        $this->assertTrue($modifier->requiresRow());
    }
}
