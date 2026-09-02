<?php

namespace Tests\Icinga\Module\Director\Form;

use Icinga\Module\Director\Forms\IcingaTemplateChoiceForm;
use Icinga\Module\Director\Objects\IcingaService;
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
            ->getAttrib('multiOptions');

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
