<?php

namespace Icinga\Module\Director\Forms;

use Icinga\Module\Director\Web\Form\DirectorObjectForm;
use Icinga\Authentication\Auth;

class DirectorDatalistForm extends DirectorObjectForm
{
    public function setup()
    {
        $this->addElement('text', 'list_name', array(
            'label'       => $this->translate('List name'),
            'description' => $this->translate(
                'Data lists are mainly used as data providers for custom variables'
                . ' presented as dropdown boxes boxes. You can manually manage'
                . ' their entries here in place, but you could also create dedicated'
                . ' sync rules after creating a new empty list. This would allow you'
                . ' to keep your available choices in sync with external data providers'
            ),
            'required'    => true,
        ));
        $this->addSimpleDisplayGroup(array('list_name'), 'list', array(
            'legend' => $this->translate('Data list')
        ));

        $this->setButtons();
    }

    public function onSuccess()
    {
        $this->object()->owner = self::username();
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
