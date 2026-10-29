<?php

// SPDX-FileCopyrightText: 2026 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Tests\Icinga\Module\Director\Form;

use Icinga\Module\Director\Forms\IcingaTemplateChoiceForm;
use Icinga\Module\Director\Objects\IcingaHost;
use Icinga\Module\Director\Objects\IcingaService;
use Icinga\Module\Director\Objects\IcingaTemplateChoiceHost;
use Icinga\Module\Director\Test\BaseTestCase;

class IcingaTemplateChoiceFormTest extends BaseTestCase
{
    protected const TEMPLATE_NAME = '___TEST___service_template_choice';

    protected $templateId;

    public function testServiceAssociatedTemplateUsesIdAsOptionKey()
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();

        $template = IcingaService::create([
            'object_name' => self::TEMPLATE_NAME,
            'object_type' => 'template',
        ], $db);
        $template->store();

        $this->templateId = $template->get('id');

        $form = IcingaTemplateChoiceForm::create('service', $db);
        $form->setup();

        $associatedTemplates = $form
            ->getElement('required_template_id')
            ->getMultiOptions();

        $members = $form
            ->getElement('members')
            ->getAttrib('multiOptions');

        $this->assertArrayHasKey(
            $this->templateId,
            $associatedTemplates
        );
        $this->assertSame(
            self::TEMPLATE_NAME,
            $associatedTemplates[$this->templateId]
        );

        $this->assertArrayHasKey(
            self::TEMPLATE_NAME,
            $members
        );
        $this->assertSame(
            self::TEMPLATE_NAME,
            $members[self::TEMPLATE_NAME]
        );
    }

    public function testEditingStoredChoiceDoesNotLookForRemovedImports()
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();

        $template = IcingaHost::create([
            'object_name' => self::TEMPLATE_NAME . '_host',
            'object_type' => 'template',
        ], $db);
        $template->store();

        $choice = IcingaTemplateChoiceHost::create([
            'object_name'          => self::TEMPLATE_NAME . '_host_choice',
            'min_required'         => 0,
            'max_allowed'          => 1,
            'required_template_id' => $template->get('id'),
        ], $db);
        $choice->setMembers([$template->getObjectName()]);
        $choice->store();

        try {
            $form = (new IcingaTemplateChoiceForm())->setDb($db)->setObject($choice);
            $choice->set('min_required', 1);

            $removedImportNames = new \ReflectionMethod($form, 'removedImportNames');
            $removedImportNames->setAccessible(true);

            $this->assertSame([], $removedImportNames->invoke($form));
        } finally {
            $choice->delete();
            $template->delete();
        }
    }

    public function tearDown(): void
    {
        if ($this->hasDb() && $this->templateId !== null) {
            IcingaService::loadWithAutoIncId(
                $this->templateId,
                $this->getDb()
            )->delete();
        }

        parent::tearDown();
    }
}
