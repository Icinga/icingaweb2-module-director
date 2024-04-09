<?php

namespace Icinga\Module\Director\Forms;

class IcingaUserGroupForm extends IcingaGroupForm
{
    /**
     * @throws \Zend_Form_Exception
     */
    public function setup()
    {
        $this->addHidden('object_type', 'object');

        $this->addElement('text', 'object_name', array(
            'label'       => $this->translate('Usergroup'),
            'required'    => true,
            'description' => $this->translate('Icinga object name for this user group')
        ));

        $this->addGroupDisplayNameElement()
             ->addZoneElements()
             ->groupMainProperties()
             ->setButtons();
    }
}
