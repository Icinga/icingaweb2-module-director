<?php

namespace Icinga\Module\Director\Forms;

use Icinga\Module\Director\Web\Form\DirectorObjectForm;

class IcingaUserGroupMemberForm extends DirectorObjectForm
{
    public function setup()
    {
        $this->addElement('select', 'usergroup_id', array(
            'label' => $this->translate('Usergroup'),
            'description' => $this->translate('The name of the usergroup')
        ));

        $this->addElement('select', 'user_id', array(
            'label' => $this->translate('User'),
            'description' => $this->translate('The name of the user')
        ));

        $this->addElement('submit', $this->translate('Store'));
    }
}
