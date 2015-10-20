<?php

namespace Icinga\Module\Director\Controllers;

use Icinga\Module\Director\Web\Controller\ActionController;

class WelcomeController extends ActionController
{
    public function indexAction()
    {
        $this->getTabs()->add('welcome', array(
            'url' => $this->getRequest()->getUrl(),
            'label' => $this->translate('Welcome')
        ))->activate('welcome');
        if (! $this->Config()->get('db', 'resource')) {
            $this->view->errorMessage = sprintf(
                $this->translate('No database resource has been configured yet. Please %s to complete your config'),
                $this->view->qlink($this->translate('click here'), 'director/settings')
            );
        }
    }
}
