<?php

namespace Tests\Icinga\Module\Director\Data;

use Icinga\Module\Director\Data\ObjectImporter;
use Icinga\Module\Director\Objects\IcingaService;
use Icinga\Module\Director\Objects\IcingaTemplateChoiceService;
use Icinga\Module\Director\Test\BaseTestCase;

class ObjectImporterTest extends BaseTestCase
{
    protected const TEMPLATE_NAME = '___TEST___object_importer_service_template';

    protected const CHOICE_NAME = '___TEST___object_importer_service_choice';

    protected $templateId;

    protected $choiceId;

    // basket restore used to throw "icinga_service has a multicolumn key,
    // array required" here, service templates can't be loaded by a plain
    // name like host templates can
    public function testServiceTemplateChoiceRestoresAssociatedTemplateByName()
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

        $plain = (object) [
            'object_name' => self::CHOICE_NAME,
            'required_template' => self::TEMPLATE_NAME,
        ];

        $importer = new ObjectImporter($db);
        $choice = $importer->import(IcingaTemplateChoiceService::class, $plain);
        $choice->store();

        $this->choiceId = $choice->get('id');

        $this->assertSame(
            (int) $this->templateId,
            (int) $choice->get('required_template_id')
        );
    }

    public function tearDown(): void
    {
        if ($this->hasDb() && $this->choiceId !== null) {
            IcingaTemplateChoiceService::loadWithAutoIncId(
                $this->choiceId,
                $this->getDb()
            )->delete();
        }

        if ($this->hasDb() && $this->templateId !== null) {
            IcingaService::loadWithAutoIncId(
                $this->templateId,
                $this->getDb()
            )->delete();
        }

        parent::tearDown();
    }
}
