<?php

// SPDX-FileCopyrightText: 2026 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Tests\Icinga\Module\Director\Form;

use Icinga\Module\Director\DataType\DataTypeArray;
use Icinga\Module\Director\Forms\IcingaServiceForm;
use Icinga\Module\Director\Objects\DirectorDatafield;
use Icinga\Module\Director\Objects\IcingaHost;
use Icinga\Module\Director\Test\BaseTestCase;

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
}
