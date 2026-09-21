<?php

// SPDX-FileCopyrightText: 2026 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Tests\Icinga\Module\Director\Web\Form;

use Icinga\Module\Director\Forms\IcingaServiceForm;
use Icinga\Module\Director\Objects\IcingaService;
use Icinga\Module\Director\Test\BaseTestCase;

class RequiredImportsWithoutTemplatesTest extends BaseTestCase
{
    private function newServiceForm(string $objectType, array $templates = []): IcingaServiceForm
    {
        $db = $this->getDb();
        $form = new class extends IcingaServiceForm {
            public array $availableTemplates = [];

            protected function enumAllowedTemplates()
            {
                return $this->availableTemplates;
            }

            protected function getObjectShortClassName()
            {
                return 'Service';
            }

            protected function addImportRemovalWarning()
            {
                // No existing object/imports in the test scenario.
            }

            public function addImportsForTest($required = null)
            {
                return $this->addImportsElement($required);
            }

            public function renderRequiredImportsForTest()
            {
                return $this->getElement('imports');
            }
        };

        $form->availableTemplates = $templates;
        $form->setObject(IcingaService::create([
            'object_name' => '___TEST___3066_new_service',
            'object_type' => $objectType,
        ], $db));

        return $form;
    }

    public function testRequiredImportsFieldIsVisibleAndValidatesEvenWithoutTemplates(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $form = $this->newServiceForm('object');
        $form->addImportsForTest();
        $imports = $form->renderRequiredImportsForTest();

        $this->assertNotNull($imports, 'The missing required Imports field hides the real validation error');
        $this->assertTrue($imports->isRequired());
        $this->assertFalse($imports->isValid([]), 'A missing template must be rejected by its visible field');
        $this->assertNotEmpty($imports->getMessages());
    }

    public function testOptionalImportsStayHiddenWhenNoTemplatesExist(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $form = $this->newServiceForm('template');
        $form->addImportsForTest();
        $this->assertNull($form->getElement('imports'));
    }

    public function testRequiredImportsRemainVisibleWhenATemplateExists(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $form = $this->newServiceForm('object', [
            '___TEST___3066_template' => '___TEST___3066_template'
        ]);
        $form->addImportsForTest();
        $this->assertTrue($form->getElement('imports')->isRequired());
    }
}
