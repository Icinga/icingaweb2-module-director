<?php

namespace Icinga\Module\Director\Controllers;

use Icinga\Module\Director\Web\Controller\ActionController;

class SettingsController extends ActionController
{
    public function indexAction()
    {
        $this->view->tabs = $this->Module()
            ->getConfigTabs()
            ->activate('config');

        $this->view->form = $this->loadForm('config')
            ->setModuleConfig($this->Config())
            ->handleRequest();
    }
}
