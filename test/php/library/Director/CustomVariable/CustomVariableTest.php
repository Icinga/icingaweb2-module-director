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
        $this->assertInstanceOf(CustomVariableNull::class, CustomVariable::create('notes', null));
    }

    public function testCreateBoolTrueReturnsBoolean(): void
    {
        $this->assertInstanceOf(
            CustomVariableBoolean::class,
            CustomVariable::create('notifications_enabled', true)
        );
    }

    public function testCreateBoolFalseReturnsBoolean(): void
    {
        $this->assertInstanceOf(
            CustomVariableBoolean::class,
            CustomVariable::create('notifications_enabled', false)
        );
    }

    public function testCreateIntegerReturnsNumber(): void
    {
        $this->assertInstanceOf(CustomVariableNumber::class, CustomVariable::create('max_check_attempts', 3));
    }

    public function testCreateFloatReturnsNumber(): void
    {
        $this->assertInstanceOf(CustomVariableNumber::class, CustomVariable::create('load_threshold', 3.14));
    }

    public function testCreateStringReturnsString(): void
    {
        $this->assertInstanceOf(CustomVariableString::class, CustomVariable::create('env', 'production'));
    }

    public function testCreateIndexedArrayReturnsArray(): void
    {
        $this->assertInstanceOf(
            CustomVariableArray::class,
            CustomVariable::create('dns_servers', ['8.8.8.8', '8.8.4.4', '1.1.1.1'])
        );
    }

    public function testCreateEmptyArrayReturnsArray(): void
    {
        $this->assertInstanceOf(CustomVariableArray::class, CustomVariable::create('excluded_checks', []));
    }

    public function testCreateAssociativeArrayReturnsDictionary(): void
    {
        $this->assertInstanceOf(
            CustomVariableDictionary::class,
            CustomVariable::create('disk_thresholds', ['warn' => '20%'])
        );
    }

    public function testCreateMixedKeyArrayReturnsDictionary(): void
    {
        // Mixed numeric and string keys → dictionary because at least one key is non-integer
        $this->assertInstanceOf(
            CustomVariableDictionary::class,
            CustomVariable::create('interfaces', [0 => 'eth0', 'label' => 'Primary Interface'])
        );
    }

    public function testCreateObjectReturnsDictionary(): void
    {
        $obj = (object) ['warn' => '20%', 'crit' => '10%'];
        $this->assertInstanceOf(CustomVariableDictionary::class, CustomVariable::create('disk_thresholds', $obj));
    }

    public function testCreatePreservesKey(): void
    {
        $var = CustomVariable::create('environment', 'production');
        $this->assertEquals('environment', $var->getKey());
    }

    public function testCreatePreservesValue(): void
    {
        $var = CustomVariable::create('env', 'production');
        $this->assertEquals('production', $var->getValue());
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
            'varname'  => 'disk_thresholds',
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
            'varname'  => 'snmp_community',
            'varvalue' => 'cGFzc3dvcmQ=',
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

    public function testClearUuidRemovesAnAlreadySetUuid(): void
    {
        $var = CustomVariable::create('env', 'production');
        $var->setUuid(Uuid::uuid4());

        $var->clearUuid();

        $this->assertNull($var->getUuid());
    }

    public function testClearUuidMarksTheVarAsModified(): void
    {
        $row = (object) [
            'format'        => 'string',
            'varname'       => 'env',
            'varvalue'      => 'prod',
            'property_uuid' => Uuid::uuid4()->getBytes(),
        ];
        $var = CustomVariable::fromDbRow($row);

        $var->clearUuid();

        $this->assertTrue($var->hasBeenModified());
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

    public function testEqualDictionariesAreEqual(): void
    {
        $a = CustomVariable::create('disk_thresholds', ['warn' => '20%', 'crit' => '10%']);
        $b = CustomVariable::create('disk_thresholds', ['warn' => '20%', 'crit' => '10%']);
        $this->assertTrue($a->equals($b));
    }

    public function testDictionaryKeyOrderDoesNotMatter(): void
    {
        // Values must match regardless of the insertion order of keys
        $a = CustomVariable::create('disk_thresholds', ['crit' => '10%', 'warn' => '20%']);
        $b = CustomVariable::create('disk_thresholds', ['warn' => '20%', 'crit' => '10%']);
        $this->assertTrue($a->equals($b));
    }

    public function testGetDbValueSerializesKeysInSortedOrder(): void
    {
        $dict = CustomVariable::create('disk_thresholds', ['warn' => '20%', 'crit' => '10%']);
        assert($dict instanceof CustomVariableDictionary);

        $this->assertSame('{"crit":"10%","warn":"20%"}', $dict->getDbValue());
    }

    public function testDictionariesWithDifferentKeysAreNotEqual(): void
    {
        $a = CustomVariable::create('disk_thresholds', ['warn' => '20%', 'crit' => '10%']);
        $b = CustomVariable::create('disk_thresholds', ['warn' => '20%']);
        $this->assertFalse($a->equals($b));
    }

    public function testDictionariesWithSameKeysDifferentValuesAreNotEqual(): void
    {
        $a = CustomVariable::create('disk_thresholds', ['warn' => '20%', 'crit' => '10%']);
        $b = CustomVariable::create('disk_thresholds', ['warn' => '30%', 'crit' => '10%']);
        $this->assertFalse($a->equals($b));
    }

    public function testDictionaryIsNotEqualToString(): void
    {
        $dict = CustomVariable::create('disk_thresholds', ['warn' => '20%']);
        $str  = CustomVariable::create('env', 'production');
        $this->assertFalse($dict->equals($str));
    }

    public function testEmptyArrayVarsAreEqual(): void
    {
        // Empty PHP arrays have no string keys so CustomVariable::create() returns
        // CustomVariableArray, not CustomVariableDictionary — both produce the same
        // db value '[]', so they must compare equal.
        $a = CustomVariable::create('disk_thresholds', []);
        $b = CustomVariable::create('disk_thresholds', []);
        $this->assertTrue($a->equals($b));
    }
}
