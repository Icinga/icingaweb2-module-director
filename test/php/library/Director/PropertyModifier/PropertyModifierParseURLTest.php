<?php

namespace Tests\Icinga\Module\Director\PropertyModifier;

use Icinga\Exception\InvalidPropertyException;
use Icinga\Module\Director\PropertyModifier\PropertyModifierParseURL;
use Icinga\Module\Director\Test\BaseTestCase;

class PropertyModifierParseURLTest extends BaseTestCase
{
    protected static $validurl = 'https://www.icinga.org/path/file.html?foo=bar#section';
    protected static $invalidurl = 'http:///www.icinga.org/';


    public function testModifierDoesNotSupportArraysItself()
    {
        $modifier = new PropertyModifierParseURL();
        $this->assertFalse($modifier->hasArraySupport());
    }

    public function testEmptyPropertyReturnsNullOnfailureNull()
    {
        $modifier = new PropertyModifierParseURL();
        $modifier->setSettings([
            'url_component' => 'query',
            'on_failure' => 'null',
        ]);

        $this->assertNull($modifier->transform(''));
    }

    public function testMissingComponentReturnsNullOnfailureNull()
    {
        $modifier = new PropertyModifierParseURL();
        $modifier->setSettings([
            'url_component' => 'query',
            'on_failure' => 'null',
        ]);

        $this->assertNull($modifier->transform('https://www.icinga.org/path/'));
    }

    public function testMissingComponentReturnsPropertyOnfailureKeep()
    {
        $modifier = new PropertyModifierParseURL();
        $modifier->setSettings([
            'url_component' => 'query',
            'on_failure' => 'keep',
        ]);

        $this->assertEquals('http://www.icinga.org/path/', $modifier->transform('http://www.icinga.org/path/'));
    }

    public function testMissingComponentThrowsExceptionOnfailureFail()
    {
        $this->expectException(InvalidPropertyException::class);

        $modifier = new PropertyModifierParseURL();
        $modifier->setSettings([
            'url_component' => 'query',
            'on_failure' => 'fail',
        ]);

        $modifier->transform('http://www.icinga.org/path/');
    }


    public function testInvalidUrlReturnsNullOnfailureNull()
    {
        $modifier = new PropertyModifierParseURL();
        $modifier->setSettings([
            'url_component' => 'host',
            'on_failure' => 'null',
        ]);

        $this->assertNull($modifier->transform(self::$invalidurl));
    }

    public function testInvalidUrlReturnsItselfOnfailureKeep()
    {
        $modifier = new PropertyModifierParseURL();
        $modifier->setSettings([
            'url_component' => 'host',
            'on_failure' => 'keep',
        ]);

        $this->assertEquals(self::$invalidurl, $modifier->transform(self::$invalidurl));
    }

    public function testInvalidUrlThrowsExceptionOnfailureFail()
    {
        $this->expectException(InvalidPropertyException::class);

        $modifier = new PropertyModifierParseURL();
        $modifier->setSettings([
            'url_component' => 'host',
            'on_failure' => 'fail',
        ]);

        $modifier->transform(self::$invalidurl);
    }


    /**
     * @dataProvider dataURLcomponentProvider
     */
    public function testSuccessfullyParse($component, $result)
    {
        $modifier = new PropertyModifierParseURL();
        $modifier->setSettings([
            'url_component' => $component,
            'on_failure' => 'null',
        ]);

        $this->assertEquals($result, $modifier->transform(self::$validurl));
    }
    public function dataURLcomponentProvider()
    {
        return [
            'scheme' => [
                    'scheme',
                    'https',
                ],
                'host' => [
                        'host',
                        'www.icinga.org',
                ],
                'port' => [
                    'port',
                    '',
                ],
                'path' => [
                        'path',
                        '/path/file.html',
                ],
                'query' => [
                        'query',
                        'foo=bar',
                ],
                'fragment' => [
                        'fragment',
                        'section',
                ],
        ];
    }
}
