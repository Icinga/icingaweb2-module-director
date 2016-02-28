<?php

namespace Icinga\Module\Director\Forms;

use Icinga\Module\Director\Web\Form\DirectorObjectForm;

class IcingaNotificationForm extends DirectorObjectForm
{
    public function setup()
    {
        $this->addObjectTypeElement();

        $this->addElement('text', 'object_name', array(
            'label'       => $this->translate('Notification'),
            'required'    => true,
            'description' => $this->translate('Icinga object name for this notification')
        ));
 
        $this->addElement('multiselect', 'states', array(
            'label'        => $this->translate('States'),
            'description'  => $this->translate('The host/service states you want to get notifications for'),
            'multiOptions' => $this->enumStateFilters(),
            'size'         => 6,
        ));

        $this->addElement('multiselect', 'types', array(
            'label'        => $this->translate('Event types'),
            'description'  => $this->translate('The event types you want to get notifications for'),
            'multiOptions' => $this->enumTypeFilters(),
            'size'         => 6,
        ));

        $this->addDisabledElement();
        $this->setButtons();
    }
}
