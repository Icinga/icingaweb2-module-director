<?php

namespace Tests\Icinga\Module\Director\Objects;

use Icinga\Module\Director\PropertyModifier\PropertyModifierArrayFilter;
use Icinga\Module\Director\Test\BaseTestCase;

class PropertyModifierArrayFilterTest extends BaseTestCase
{
    /**
     * Allowed settings:
     *
     * filter_method: wildcard, regex
     * filter_string
     *
     * policy: keep, reject
     * when_empty: empty_array, null
     */

    /** @var array */
    private $testArray = array(
        'www.example.com',
        'example.com',
        'www',
        'wwexample.com',
        'example.www',
        '',
    );

    public function testKeepMatchingWildcards()
    {
        $modifier = new PropertyModifierArrayFilter();
        $modifier->setSettings(array(
            'filter_method' => 'wildcard',
            'filter_string' => 'www*',
            'policy'        => 'keep',
            'when_empty'    => 'empty_array',
        ));

        $this->assertEquals(
            array('www.example.com', 'www'),
            $modifier->transform($this->testArray)
        );
    }

    public function testRejectMatchingWildcards()
    {
        $modifier = new PropertyModifierArrayFilter();
        $modifier->setSettings(array(
            'filter_method' => 'wildcard',
            'filter_string' => 'www*',
            'policy'        => 'reject',
            'when_empty'    => 'empty_array',
        ));

        $this->assertEquals(
            array('example.com', 'wwexample.com', 'example.www', ''),
            $modifier->transform($this->testArray)
        );
    }

    public function testKeepMatchingRegularExpression()
    {
        $modifier = new PropertyModifierArrayFilter();
        $modifier->setSettings(array(
            'filter_method' => 'regex',
            'filter_string' => '/^w{3}.*/',
            'policy'        => 'keep',
            'when_empty'    => 'empty_array',
        ));

        $this->assertEquals(
            array('www.example.com', 'www'),
            $modifier->transform($this->testArray)
        );
    }

    public function testRejectMatchingRegularExpression()
    {
        $modifier = new PropertyModifierArrayFilter();
        $modifier->setSettings(array(
            'filter_method' => 'regex',
            'filter_string' => '/^w{3}.*/',
            'policy'        => 'reject',
            'when_empty'    => 'empty_array',
        ));

        $this->assertEquals(
            array('example.com', 'wwexample.com', 'example.www', ''),
            $modifier->transform($this->testArray)
        );
    }

    public function testGivesEmptyArrayOrNullAccordingToConfig()
    {
        $modifier = new PropertyModifierArrayFilter();
        $modifier->setSettings(array(
            'filter_method' => 'wildcard',
            'filter_string' => 'no-match',
            'policy'        => 'keep',
            'when_empty'    => 'empty_array',
        ));

        $this->assertEquals(
            array(),
            $modifier->transform($this->testArray)
        );

        $modifier->setSetting('when_empty', 'null');
        $this->assertNull(
            $modifier->transform($this->testArray)
        );
    }

    public function testAnnouncesArraySupport()
    {
        $modifier = new PropertyModifierArrayFilter();
        $this->assertTrue($modifier->hasArraySupport());
    }
}
