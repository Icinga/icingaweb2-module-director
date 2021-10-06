<?php

namespace Icinga\Module\Director\Controllers;

use Icinga\Module\Director\Forms\IcingaTemplateChoiceForm;
use Icinga\Module\Director\Web\Controller\ActionController;
use Icinga\Module\Director\Web\Controller\BranchHelper;

class TemplatechoiceController extends ActionController
{
    use BranchHelper;

    protected function checkDirectorPermissions()
    {
        $this->assertPermission('director/admin');
    }

    public function hostAction()
    {
        $this->prepare('host', $this->translate('Host template choice'));
    }

    public function serviceAction()
    {
        $this->prepare('service', $this->translate('Service template choice'));
    }

    protected function prepare($type, $title)
    {
        $this->addSingleTab('Choice')
            ->addTitle($title);
        $form = IcingaTemplateChoiceForm::create($type, $this->db())
            ->optionallyLoad($this->params->get('name'))
            ->setListUrl("director/templatechoices/$type")
            ->handleRequest();
        if ($this->showNotInBranch($this->translate('Modifying Template Choices'))) {
            return;
        }
        $this->content()->add($form);
    }
}
