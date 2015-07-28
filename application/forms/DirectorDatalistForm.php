<?php

namespace Icinga\Module\Director\Forms;

use Icinga\Module\Director\Web\Form\DirectorObjectForm;
use Icinga\Authentication\Manager as Auth;

class DirectorDatalistForm extends DirectorObjectForm
{
    public function setup()
    {
        $this->addElement('text', 'list_name', array(
            'label' => $this->translate('List name')
        ));

        $this->addElement('hidden', 'owner');
    }

    public function onSuccess()
    {
        $this->addHidden('owner', self::username());
        parent::onSuccess();
    }

    protected static function username()
    {
        $auth = Auth::getInstance();
        if ($auth->isAuthenticated()) {
            return $auth->getUser()->getUsername();
        } else {
            return '<unknown>';
        }
    }
}
