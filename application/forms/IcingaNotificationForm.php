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
 

        $this->addDisabledElement();
        $this->setButtons();
    }
}
