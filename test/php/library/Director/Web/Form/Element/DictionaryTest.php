<?php

namespace Tests\Icinga\Module\Director\Web\Form\Element;

use Icinga\Module\Director\Test\BaseTestCase;
use Icinga\Module\Director\Web\Form\Element\Dictionary;

class DictionaryTest extends BaseTestCase
{
    protected $dictionaryInstance;

    public function setUp() {
        parent::setUp();
        $this->dictionaryInstance = new Dictionary('fake_name');
    }

    public function testValidDictionary() {
        $this->dictionaryInstance->setDefaultValue([
            'key_one' => 0,
            'key_two' => ''
        ]);
        $this->dictionaryInstance->setFieldSettingsMap([
            'key_one' => ['is_required' => true],
            'key_two' => ['is_required' => true],
        ]);

        $this->dictionaryInstance->isValid([
            'key_one' => 42,
            'key_two' => 'foobar'
        ]);

        $this->assertFalse($this->dictionaryInstance->hasErrors());
    }

    public function testDictionaryWithMissingKey() {
        $this->dictionaryInstance->setDefaultValue([
            'key_one' => 0,
            'key_two' => '',
            'key_three' => ''
        ]);
        $this->dictionaryInstance->setFieldSettingsMap([
            'key_one' => ['is_required' => false],
            'key_two' => ['is_required' => false],
            'key_three' => ['is_required' => false],
        ]);

        $this->dictionaryInstance->isValid([
            'key_one' => 42,
            'key_two' => 'foobar'
        ]);

        $this->assertTrue($this->dictionaryInstance->hasErrors());
        $errors = $this->dictionaryInstance->getMessages();
        $this->assertEquals(1, count($errors));
        $this->assertEquals('Key \'key_three\' is missing', $errors[0]);
    }

    public function testDictionaryWithMissingSubkey() {
        $this->dictionaryInstance->setDefaultValue([
            'key_one' => 0,
            'key_two' => [
                'sub_key_one' => ''
            ]
        ]);
        $this->dictionaryInstance->setFieldSettingsMap([
            'key_one' => ['is_required' => true],
            'key_two' => ['is_required' => true],
            'key_two.sub_key_one' => ['is_required' => false],
        ]);

        $this->dictionaryInstance->isValid([
            'key_one' => 0,
            'key_two' => []
        ]);

        $this->assertTrue($this->dictionaryInstance->hasErrors());
        $errors = $this->dictionaryInstance->getMessages();
        $this->assertEquals(1, count($errors));
        $this->assertEquals('Key \'key_two.sub_key_one\' is missing', $errors[0]);
    }

    public function testDictionaryWithKeyTypeMismatch() {
        $this->dictionaryInstance->setDefaultValue([
            'key_one' => 0
        ]);
        $this->dictionaryInstance->setFieldSettingsMap([
            'key_one' => ['is_required' => false]
        ]);

        $this->dictionaryInstance->isValid([
            'key_one' => 'oh no a string'
        ]);

        $this->assertTrue($this->dictionaryInstance->hasErrors());
        $errors = $this->dictionaryInstance->getMessages();
        $this->assertEquals(1, count($errors));
        $this->assertEquals('Type mismatch, \'key_one\' is expected to be a \'integer\', \'string\' given', $errors[0]);
    }

    public function testDictionaryValidationWithRawJson() {
        $this->dictionaryInstance->setDefaultValue([
            'key_one' => 0,
            'key_two' => ''
        ]);
        $this->dictionaryInstance->setFieldSettingsMap([
            'key_one' => ['is_required' => false],
            'key_two' => ['is_required' => false],
        ]);

        $this->dictionaryInstance->isValid('{"key_one" : 42,"key_two" : "foobar"}');

        $this->assertFalse($this->dictionaryInstance->hasErrors());
    }

    public function testRequiredFieldCantBeNull() {
        $this->dictionaryInstance->setDefaultValue([
            'key_one' => [
                'sub_key_one' => ''
            ]
        ]);
        $this->dictionaryInstance->setFieldSettingsMap([
            'key_one' => ['is_required' => true],
            'key_one.sub_key_one' => ['is_required' => true],
        ]);

        $this->dictionaryInstance->isValid([
            'key_one' => [
                'sub_key_one' => null
            ]
        ]);

        $this->assertTrue($this->dictionaryInstance->hasErrors());
        $errors = $this->dictionaryInstance->getMessages();
        $this->assertEquals(1, count($errors));
        $this->assertEquals('Key \'key_one.sub_key_one\' is required and cannot be NULL', $errors[0]);
    }

    public function testNonRequiredFieldCanBeNull() {
        $this->dictionaryInstance->setDefaultValue([
            'key_one' => [
                'sub_key_one' => ''
            ]
        ]);
        $this->dictionaryInstance->setFieldSettingsMap([
            'key_one' => ['is_required' => true],
            'key_one.sub_key_one' => ['is_required' => false],
        ]);

        $this->dictionaryInstance->isValid([
            'key_one' => [
                'sub_key_one' => null
            ]
        ]);

        $this->assertFalse($this->dictionaryInstance->hasErrors());
    }

    public function testConfigCanBeSettedForExpressions() {
        $this->dictionaryInstance->setDefaultValue([
            'key_one' => ''
        ]);

        $this->dictionaryInstance->isValid('$config$');

        $this->assertFalse($this->dictionaryInstance->hasErrors());

        $this->dictionaryInstance->setValue('$config$');

        $this->assertEquals('$config$', $this->dictionaryInstance->getValue());
    }

    public function testValueIsNullIfNotChanged() {
        $this->dictionaryInstance->setDefaultValue([
            'key_one' => [
                'sub_key_one' => ''
            ]
        ]);

        $this->assertEquals(null, $this->dictionaryInstance->getValue());

        $this->dictionaryInstance->setValue([
            'key_one' => [
                'sub_key_one' => 'some value'
            ]
        ]);

        $this->assertEquals('some value', $this->dictionaryInstance->getValue()['key_one']['sub_key_one']);
    }
}