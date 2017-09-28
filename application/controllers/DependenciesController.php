<?php

namespace Icinga\Module\Director\Controllers;

use Icinga\Module\Director\Web\Controller\ObjectsController;
use ipl\Html\Html;

class DependenciesController extends ObjectsController
{
    protected function addObjectsTabs()
    {
        $res = parent::addObjectsTabs();
        $this->tabs()->remove('index');
        return $res;
    }

    public function applyrulesAction()
    {
        $this->content()->add(Html::tag(
            'p',
            ['class' => 'warning'],
            $this->translate('This feature is still experimental')
        ));

        parent::applyrulesAction();
    }
}
