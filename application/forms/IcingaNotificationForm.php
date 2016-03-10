<?php

namespace Icinga\Module\Director\Forms;

use Icinga\Module\Director\Web\Form\DirectorObjectForm;

class IcingaNotificationForm extends DirectorObjectForm
{
    public function setup()
    {
        $this->addObjectTypeElement();
        if (! $this->hasObjectType()) {
            $this->groupMainProperties();
            return;
        }

        $this->addElement('text', 'object_name', array(
            'label'       => $this->translate('Notification'),
            'required'    => true,
            'description' => $this->translate('Icinga object name for this notification')
        ));
 
        $this->addDisabledElement()
             ->addImportsElement()
             ->addDisabledElement()
             ->addEventFilterElements()
             ->groupMainProperties()
             ->setButtons();
    }
}
