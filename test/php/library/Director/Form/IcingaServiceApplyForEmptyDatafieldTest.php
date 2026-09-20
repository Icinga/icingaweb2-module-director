<?php

// SPDX-FileCopyrightText: 2026 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Tests\Icinga\Module\Director\Form;

use Icinga\Module\Director\DataType\DataTypeArray;
use Icinga\Module\Director\DataType\DataTypeDatalist;
use Icinga\Module\Director\DataType\DataTypeSqlQuery;
use Icinga\Module\Director\DataType\DataTypeString;
use Icinga\Module\Director\Db\DbUtil;
use Icinga\Module\Director\Forms\IcingaServiceForm;
use Icinga\Module\Director\Objects\DirectorDatafield;
use Icinga\Module\Director\Objects\DirectorProperty;
use Icinga\Module\Director\Objects\IcingaHost;
use Icinga\Module\Director\Test\BaseTestCase;
use Ramsey\Uuid\Uuid;

class IcingaServiceApplyForEmptyDatafieldTest extends BaseTestCase
{
    public function testAttachedHostTemplateArrayFieldIsSelectableWithoutAnyValue(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();
        $dba = $db->getDbAdapter();
        $name = '___TEST___apply_for_no_value_3072';
        $host = IcingaHost::create([
            'object_name' => '___TEST___host_template_3072',
            'object_type' => 'template',
        ], $db);
        $host->store();

        $field = DirectorDatafield::create([
            'varname' => $name,
            'caption' => 'Optional arrays',
            'datatype' => DataTypeArray::class,
        ], $db);
        $field->store();

        try {
            $dba->insert('icinga_host_field', [
                'host_id' => $host->get('id'),
                'datafield_id' => $field->get('id'),
                'is_required' => 'n',
            ]);

            $this->assertSame(
                0,
                (int) $dba->fetchOne(
                    $dba->select()->from('icinga_host_var', 'COUNT(*)')->where('varname = ?', $name)
                )
            );

            $form = IcingaServiceForm::load()->setDb($db);
            $options = array_values(self::callMethod($form, 'applyForVars', []))[0];
            $this->assertArrayHasKey(
                'host.vars.' . $name,
                $options,
                'Host-template array fields must be offered without requiring data on individual hosts'
            );
        } finally {
            $field->delete();
            $host->delete();
        }
    }
    public function testAttachedArrayValuedDatafieldsWithNoHostValueAreSelectable(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();
        $dba = $db->getDbAdapter();
        $host = IcingaHost::create([
            'object_name' => '___TEST___array_field_types_3072',
            'object_type' => 'template',
        ], $db);
        $host->store();
        $fields = [];

        try {
            foreach ([
                'datalist_array' => [DataTypeDatalist::class, 'array'],
                'sqlquery_array' => [DataTypeSqlQuery::class, 'array'],
                'datalist_scalar' => [DataTypeDatalist::class, 'string'],
                'sqlquery_scalar' => [DataTypeSqlQuery::class, 'string'],
                'plain_scalar' => [DataTypeString::class, null],
            ] as $suffix => [$type, $valueType]) {
                $field = DirectorDatafield::create([
                    'varname' => '___TEST___3072_' . $suffix,
                    'caption' => $suffix,
                    'datatype' => $type,
                ], $db);
                if ($valueType !== null) {
                    $field->setSettings(['data_type' => $valueType]);
                }
                $field->store();
                $fields[] = $field;
                $dba->insert('icinga_host_field', [
                    'host_id' => $host->get('id'),
                    'datafield_id' => $field->get('id'),
                    'is_required' => 'n',
                ]);
            }

            $options = array_values(self::callMethod(
                IcingaServiceForm::load()->setDb($db),
                'applyForVars',
                []
            ))[0];

            $this->assertArrayHasKey('host.vars.___TEST___3072_datalist_array', $options);
            $this->assertArrayHasKey('host.vars.___TEST___3072_sqlquery_array', $options);
            $this->assertArrayNotHasKey('host.vars.___TEST___3072_datalist_scalar', $options);
            $this->assertArrayNotHasKey('host.vars.___TEST___3072_sqlquery_scalar', $options);
            $this->assertArrayNotHasKey('host.vars.___TEST___3072_plain_scalar', $options);
        } finally {
            foreach ($fields as $field) {
                $field->delete();
            }
            $host->delete();
        }
    }

    public function testUnpopulatedArrayPropertyShapesAreSelectable(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();
        $dba = $db->getDbAdapter();
        $host = IcingaHost::create([
            'object_name' => '___TEST___array_property_types_3072',
            'object_type' => 'template',
        ], $db);
        $host->store();
        $properties = [];

        try {
            foreach ([
                'fixed_array' => ['fixed-array', null],
                'datalist_array' => ['datalist-strict', 'dynamic-array'],
                'datalist_scalar' => ['datalist-strict', 'string'],
                'plain_scalar' => ['string', null],
            ] as $suffix => [$type, $itemType]) {
                $property = DirectorProperty::create([
                    'uuid' => Uuid::uuid4()->getBytes(),
                    'key_name' => '___TEST___3072_property_' . $suffix,
                    'value_type' => $type,
                ], $db);
                $property->store();
                $properties[] = $property;
                if ($itemType !== null) {
                    DirectorProperty::create([
                        'uuid' => Uuid::uuid4()->getBytes(),
                        'parent_uuid' => $property->get('uuid'),
                        'key_name' => '0',
                        'value_type' => $itemType,
                    ], $db)->store();
                }
                $dba->insert('icinga_host_property', [
                    'host_uuid' => DbUtil::quoteBinaryCompat(DbUtil::binaryResult($host->get('uuid')), $dba),
                    'property_uuid' => DbUtil::quoteBinaryCompat($property->get('uuid'), $dba),
                ]);
            }

            $options = array_values(self::callMethod(
                IcingaServiceForm::load()->setDb($db),
                'applyForVars',
                []
            ))[0];

            $this->assertArrayHasKey('host.vars.___TEST___3072_property_fixed_array', $options);
            $this->assertArrayHasKey('host.vars.___TEST___3072_property_datalist_array', $options);
            $this->assertArrayNotHasKey('host.vars.___TEST___3072_property_datalist_scalar', $options);
            $this->assertArrayNotHasKey('host.vars.___TEST___3072_property_plain_scalar', $options);
        } finally {
            foreach ($properties as $property) {
                $property->delete();
            }
            $host->delete();
        }
    }

}
