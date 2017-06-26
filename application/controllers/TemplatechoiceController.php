<?php

namespace Icinga\Module\Director\Controllers;

use Icinga\Module\Director\Objects\IcingaTemplateChoiceHost;
use Icinga\Module\Director\Web\Controller\ActionController;

class TemplatechoiceController extends ActionController
{
    protected function checkDirectorPermissions()
    {
        $this->assertPermission('director/admin');
    }

    public function hostAction()
    {
        $this->addSingleTab('Choice')
             ->addTitle($this->translate('Host template choice'));
        $this->content()->add(
            $form = $this->loadForm('IcingaTemplateChoiceHost')
                ->setDb($this->db())
        );
        if ($name = $this->params->get('name')) {
            $form->setObject(IcingaTemplateChoiceHost::load($name, $this->db()));
        }
        $form->handleRequest();
    }
}
