<?php

// SPDX-FileCopyrightText: 2026 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Tests\Icinga\Module\Director\CustomVariable;

use InvalidArgumentException;
use Icinga\Module\Director\CustomVariable\CustomVariable;
use Icinga\Module\Director\CustomVariable\CustomVariableArray;
use Icinga\Module\Director\CustomVariable\CustomVariableBoolean;
use Icinga\Module\Director\CustomVariable\CustomVariableDictionary;
use Icinga\Module\Director\CustomVariable\CustomVariableNull;
use Icinga\Module\Director\CustomVariable\CustomVariableNumber;
use Icinga\Module\Director\CustomVariable\CustomVariableString;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;

class CustomVariableTest extends TestCase
{
    // -------------------------------------------------------------------------
    // CustomVariable::create() — type dispatch
    // -------------------------------------------------------------------------

    public function testCreateNullReturnsNull(): void
    {
        $this->assertInstanceOf(CustomVariableNull::class, CustomVariable::create('k', null));
    }

    public function testCreateBoolTrueReturnsBoolean(): void
    {
        $this->assertInstanceOf(CustomVariableBoolean::class, CustomVariable::create('k', true));
    }

    public function testCreateBoolFalseReturnsBoolean(): void
    {
        $this->assertInstanceOf(CustomVariableBoolean::class, CustomVariable::create('k', false));
    }

    public function testCreateIntegerReturnsNumber(): void
    {
        $this->assertInstanceOf(CustomVariableNumber::class, CustomVariable::create('k', 42));
    }

    public function testCreateFloatReturnsNumber(): void
    {
        $this->assertInstanceOf(CustomVariableNumber::class, CustomVariable::create('k', 3.14));
    }

    public function testCreateStringReturnsString(): void
    {
        $this->assertInstanceOf(CustomVariableString::class, CustomVariable::create('k', 'hello'));
    }

    public function testCreateIndexedArrayReturnsArray(): void
    {
        $this->assertInstanceOf(CustomVariableArray::class, CustomVariable::create('k', ['a', 'b', 'c']));
    }

    public function testCreateEmptyArrayReturnsArray(): void
    {
        $this->assertInstanceOf(CustomVariableArray::class, CustomVariable::create('k', []));
    }

    public function testCreateNumericStringKeyedArrayReturnsArray(): void
    {
        // Arrays whose keys are numeric strings ('0', '1', …) are treated as arrays, not dictionaries
        $this->assertInstanceOf(CustomVariableArray::class, CustomVariable::create('k', ['0' => 'x', '1' => 'y']));
    }

    public function testCreateAssociativeArrayReturnsDictionary(): void
    {
        $this->assertInstanceOf(CustomVariableDictionary::class, CustomVariable::create('k', ['key' => 'val']));
    }

    public function testCreateMixedKeyArrayReturnsDictionary(): void
    {
        // Mixed numeric and string keys → dictionary because at least one key is non-integer
        $this->assertInstanceOf(
            CustomVariableDictionary::class,
            CustomVariable::create('k', [0 => 'a', 'label' => 'b'])
        );
    }

    public function testCreateObjectReturnsDictionary(): void
    {
        $obj = (object) ['warn' => '20%', 'crit' => '10%'];
        $this->assertInstanceOf(CustomVariableDictionary::class, CustomVariable::create('k', $obj));
    }

    public function testCreatePreservesKey(): void
    {
        $var = CustomVariable::create('my_key', 'value');
        $this->assertEquals('my_key', $var->getKey());
    }

    public function testCreatePreservesValue(): void
    {
        $var = CustomVariable::create('k', 'hello');
        $this->assertEquals('hello', $var->getValue());
    }

    // -------------------------------------------------------------------------
    // CustomVariable::fromDbRow() — format dispatch and field hydration
    // -------------------------------------------------------------------------

    public function testFromDbRowStringFormatCreatesString(): void
    {
        $row = (object) [
            'format'   => 'string',
            'varname'  => 'env',
            'varvalue' => 'production',
        ];

        $var = CustomVariable::fromDbRow($row);
        $this->assertInstanceOf(CustomVariableString::class, $var);
        $this->assertEquals('production', $var->getValue());
    }

    public function testFromDbRowJsonStringCreatesString(): void
    {
        $row = (object) [
            'format'   => 'json',
            'varname'  => 'env',
            'varvalue' => json_encode('production'),
        ];

        $var = CustomVariable::fromDbRow($row);
        $this->assertInstanceOf(CustomVariableString::class, $var);
        $this->assertEquals('production', $var->getValue());
    }

    public function testFromDbRowJsonArrayCreatesArray(): void
    {
        $row = (object) [
            'format'   => 'json',
            'varname'  => 'vhosts',
            'varvalue' => json_encode(['web01', 'web02']),
        ];

        $var = CustomVariable::fromDbRow($row);
        $this->assertInstanceOf(CustomVariableArray::class, $var);
    }

    public function testFromDbRowJsonObjectCreatesDictionary(): void
    {
        $row = (object) [
            'format'   => 'json',
            'varname'  => 'thresholds',
            'varvalue' => json_encode(['warn' => '20%', 'crit' => '10%']),
        ];

        $var = CustomVariable::fromDbRow($row);
        $this->assertInstanceOf(CustomVariableDictionary::class, $var);
    }

    public function testFromDbRowExpressionThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);

        CustomVariable::fromDbRow((object) [
            'format'   => 'expression',
            'varname'  => 'expr',
            'varvalue' => '{{ 1 + 1 }}',
        ]);
    }

    public function testFromDbRowUnknownFormatThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);

        CustomVariable::fromDbRow((object) [
            'format'   => 'binary',
            'varname'  => 'data',
            'varvalue' => 'abc',
        ]);
    }

    public function testFromDbRowSetsChecksumWhenPresent(): void
    {
        $checksum = sha1('test', true);
        $row = (object) [
            'format'   => 'string',
            'varname'  => 'env',
            'varvalue' => 'prod',
            'checksum' => $checksum,
        ];

        $var = CustomVariable::fromDbRow($row);
        $this->assertEquals($checksum, $var->getChecksum());
    }

    public function testFromDbRowDoesNotSetChecksumWhenAbsent(): void
    {
        $row = (object) [
            'format'   => 'string',
            'varname'  => 'env',
            'varvalue' => 'prod',
        ];

        $var = CustomVariable::fromDbRow($row);
        $this->assertNull($var->getChecksum());
    }

    public function testFromDbRowSetsUuidWhenPropertyUuidPresent(): void
    {
        $uuid = Uuid::uuid4();
        $row = (object) [
            'format'        => 'string',
            'varname'       => 'env',
            'varvalue'      => 'prod',
            'property_uuid' => $uuid->getBytes(),
        ];

        $var = CustomVariable::fromDbRow($row);
        $this->assertNotNull($var->getUuid());
        $this->assertEquals($uuid->toString(), $var->getUuid()->toString());
    }

    public function testFromDbRowDoesNotSetUuidWhenPropertyUuidAbsent(): void
    {
        $row = (object) [
            'format'   => 'string',
            'varname'  => 'env',
            'varvalue' => 'prod',
        ];

        $var = CustomVariable::fromDbRow($row);
        $this->assertNull($var->getUuid());
    }

    public function testFromDbRowDoesNotSetUuidWhenPropertyUuidNull(): void
    {
        $row = (object) [
            'format'        => 'string',
            'varname'       => 'env',
            'varvalue'      => 'prod',
            'property_uuid' => null,
        ];

        $var = CustomVariable::fromDbRow($row);
        $this->assertNull($var->getUuid());
    }

    public function testFromDbRowMarksVarAsLoadedFromDb(): void
    {
        $row = (object) [
            'format'   => 'string',
            'varname'  => 'env',
            'varvalue' => 'prod',
        ];

        $var = CustomVariable::fromDbRow($row);
        $this->assertFalse($var->isNew());
    }

    public function testFromDbRowMarksVarAsUnmodified(): void
    {
        $row = (object) [
            'format'   => 'string',
            'varname'  => 'env',
            'varvalue' => 'prod',
        ];

        $var = CustomVariable::fromDbRow($row);
        $this->assertFalse($var->hasBeenModified());
    }

    // -------------------------------------------------------------------------
    // CustomVariableDictionary::equals()
    // -------------------------------------------------------------------------

    public function testDictionaryEqualsItself(): void
    {
        $dict = CustomVariable::create('k', ['warn' => '20%', 'crit' => '10%']);
        assert($dict instanceof CustomVariableDictionary);
        $this->assertTrue($dict->equals($dict));
    }

    public function testEqualDictionariesAreEqual(): void
    {
        $a = CustomVariable::create('k', ['warn' => '20%', 'crit' => '10%']);
        $b = CustomVariable::create('k', ['warn' => '20%', 'crit' => '10%']);
        $this->assertTrue($a->equals($b));
    }

    public function testDictionaryKeyOrderDoesNotMatter(): void
    {
        // Values must match regardless of the insertion order of keys
        $a = CustomVariable::create('k', ['crit' => '10%', 'warn' => '20%']);
        $b = CustomVariable::create('k', ['warn' => '20%', 'crit' => '10%']);
        $this->assertTrue($a->equals($b));
    }

    public function testDictionariesWithDifferentKeysAreNotEqual(): void
    {
        $a = CustomVariable::create('k', ['warn' => '20%', 'crit' => '10%']);
        $b = CustomVariable::create('k', ['warn' => '20%']);
        $this->assertFalse($a->equals($b));
    }

    public function testDictionariesWithSameKeysDifferentValuesAreNotEqual(): void
    {
        $a = CustomVariable::create('k', ['warn' => '20%', 'crit' => '10%']);
        $b = CustomVariable::create('k', ['warn' => '30%', 'crit' => '10%']);
        $this->assertFalse($a->equals($b));
    }

    public function testDictionaryIsNotEqualToString(): void
    {
        $dict = CustomVariable::create('k', ['warn' => '20%']);
        $str  = CustomVariable::create('k', 'hello');
        $this->assertFalse($dict->equals($str));
    }

    public function testEmptyArrayVarsAreEqual(): void
    {
        // Empty PHP arrays have no string keys so CustomVariable::create() returns
        // CustomVariableArray, not CustomVariableDictionary — both produce the same
        // db value '[]', so they must compare equal.
        $a = CustomVariable::create('k', []);
        $b = CustomVariable::create('k', []);
        $this->assertTrue($a->equals($b));
    }
}
