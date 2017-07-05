<?php

namespace Icinga\Module\Director\Controllers;

use Icinga\Module\Director\Forms\IcingaTemplateChoiceForm;
use Icinga\Module\Director\Objects\IcingaTemplateChoiceService;
use Icinga\Module\Director\Web\Controller\ActionController;

class TemplatechoiceController extends ActionController
{
    protected function checkDirectorPermissions()
    {
        $this->assertPermission('director/admin');
    }

    public function hostAction()
    {
        $form = IcingaTemplateChoiceForm::create('host', $this->db())
            ->optionallyLoad($this->params->get('name'))
            ->handleRequest();
        $this->addSingleTab('Choice')
             ->addTitle($this->translate('Host template choice'))
             ->content()->add($form);
    }

    public function serviceAction()
    {
        $form = IcingaTemplateChoiceForm::create('service', $this->db())
            ->optionallyLoad($this->params->get('name'))
            ->handleRequest();
        $this->addSingleTab('Choice')
            ->addTitle($this->translate('Service template choice'))
            ->content()->add($form);
    }
}
