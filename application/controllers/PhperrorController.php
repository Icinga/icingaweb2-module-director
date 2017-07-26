<?php

namespace Icinga\Module\Director\Controllers;

use Icinga\Web\Controller;

class PhperrorController extends Controller
{
    public function errorAction()
    {
        $this->getTabs()->add('error', array(
            'label' => $this->translate('Error'),
            'url'   => $this->getRequest()->getUrl()
        ))->activate('error');
        $msg = $this->translate(
            "PHP version 5.4.x is required for Director >= 1.4.0, you're running %s."
            . ' Please either upgrade PHP or downgrade Icinga Director'
        );
        $this->view->title = $this->translate('Unsatisfied dependencies');
        $this->view->message = sprintf($msg, PHP_VERSION);
    }
}
