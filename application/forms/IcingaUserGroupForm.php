<?php

namespace Icinga\Module\Director\Forms;

use Icinga\Module\Director\Web\Form\DirectorObjectForm;

class IcingaUserGroupForm extends DirectorObjectForm
{
    public function setup()
    {
        $this->addHidden('object_type', 'object');

        $this->addElement('text', 'object_name', array(
            'label'       => $this->translate('Usergroup'),
            'required'    => true,
            'description' => $this->translate('Icinga object name for this user group')
        ));

        $this->addGroupDisplayNameElement()
             ->setButtons();

    }
}
