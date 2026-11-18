<?php

// SPDX-FileCopyrightText: 2026 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Tests\Icinga\Module\Director\Form;

use Icinga\Module\Director\Forms\IcingaServiceForm;
use Icinga\Module\Director\Objects\IcingaHost;
use Icinga\Module\Director\Objects\IcingaService;
use Icinga\Module\Director\Objects\IcingaServiceSet;
use Icinga\Module\Director\Test\BaseTestCase;

class IcingaServiceSetMemberFormTest extends BaseTestCase
{
    public function testAddingMemberKeepsSelectedSetWhenHostHasSameNamedSet(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();
        $name = '___TEST___set_member_form';
        $set = IcingaServiceSet::create([
            'object_name' => $name,
            'object_type' => 'template',
        ], $db);
        $set->store();

        $host = IcingaHost::create([
            'object_name' => '___TEST___set_member_host_template',
            'object_type' => 'template',
        ], $db);
        $host->store();

        $hostSet = IcingaServiceSet::create([
            'object_name' => $name,
            'object_type' => 'object',
            'host_id' => $host->get('id'),
            'imports' => $name,
        ], $db);
        $hostSet->store();

        try {
            $this->assertNotSame($set->get('id'), $hostSet->get('id'));
            $this->assertSame($name, $hostSet->getObjectName());

            $form = IcingaServiceForm::load()->setDb($db);
            $form->setObject(IcingaService::create(['object_type' => 'apply'], $db));
            $form->setServiceSet($set);
            $form->setup();

            $relatedSetId = $form->getElement('service_set_id');
            $this->assertNotNull($relatedSetId, 'The selected set must be identified by ID, not its shared name');
            $this->assertSame((string) $set->get('id'), (string) $relatedSetId->getValue());
            $this->assertNull($form->getElement('service_set'));
        } finally {
            $hostSet->delete();
            $host->delete();
            $set->delete();
        }
    }
}
