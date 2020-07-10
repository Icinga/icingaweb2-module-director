<?php

namespace Tests\Icinga\Module\Director\IcingaConfig;

use Icinga\Module\Director\Data\RecursiveUtf8Validator;
use Icinga\Module\Director\Test\BaseTestCase;

class RecursiveUtf8ValidatorTest extends BaseTestCase
{
    /**
     * @expectedException \InvalidArgumentException
     */
    public function testDetectInvalidUtf8Character()
    {
        RecursiveUtf8Validator::validateRows([
            (object) [
                'name'  => 'test 1',
                'value' => 'something',
            ],
            (object) [
                'name'  => 'test 2',
                'value' => "some\xa1\xa2thing",
            ],
        ]);
    }

    public function testAcceptValidUtf8Characters()
    {
        $this->assertTrue(RecursiveUtf8Validator::validateRows([
            (object) [
                'name'  => 'test 1',
                'value' => "Some 🍻",
            ],
            (object) [
                'name'  => 'test 2',
                'value' => [
                    (object) [
                        'its' => true,
                        ['💩']
                    ]
                ],
            ],
        ]));
    }
}
