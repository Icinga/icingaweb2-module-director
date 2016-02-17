<?php

namespace Icinga\Module\Director\Forms;

use Icinga\Module\Director\Web\Form\DirectorObjectForm;

class IcingaApiUserForm extends DirectorObjectForm
{
    public function setup()
    {
        $this->addHidden('object_type', 'external_object');

        $this->addElement('text', 'object_name', array(
            'label'    => $this->translate('Name'),
            'required' => true,
        ));

        $this->addElement('password', 'password', array(
            'label'    => $this->translate('Password'),
            'required' => true,
        ));

        $this->setButtons();
    }
}
