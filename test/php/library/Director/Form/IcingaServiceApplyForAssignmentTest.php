<?php

// SPDX-FileCopyrightText: 2026 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Tests\Icinga\Module\Director\Form;

use Icinga\Module\Director\Forms\IcingaServiceForm;
use Icinga\Module\Director\Objects\IcingaService;
use Icinga\Module\Director\Test\BaseTestCase;

class IcingaServiceApplyForAssignmentTest extends BaseTestCase
{
    public function testApplyForRuleDoesNotRequireAssignWhere(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();
        $service = IcingaService::create([
            'object_type' => 'apply',
            'object_name' => '___TEST___optional_assign_for',
            'apply_for' => 'host.vars.___TEST___array',
        ], $db);
        $form = IcingaServiceForm::load()->setDb($db)->setObject($service);
        $form->setup();

        $assign = $form->getElement('assign_filter');
        $this->assertNotNull($assign);
        $this->assertFalse($assign->isRequired(), 'Apply For already scopes the rule by the host array');

        $rendered = (string) $service;
        $this->assertStringContainsString('for (value in host.vars.___TEST___array)', $rendered);
        $this->assertStringNotContainsString('assign where', $rendered);
    }

    public function testOrdinaryServiceApplyRuleStillRequiresAssignWhere(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();
        $service = IcingaService::create([
            'object_type' => 'apply',
            'object_name' => '___TEST___required_assign',
        ], $db);
        $form = IcingaServiceForm::load()->setDb($db)->setObject($service);
        $form->setup();

        $assign = $form->getElement('assign_filter');
        $this->assertNotNull($assign);
        $this->assertTrue($assign->isRequired(), 'Unscoped Apply rules must retain the existing validation');
    }
}
