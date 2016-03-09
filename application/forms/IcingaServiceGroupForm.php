<?php

namespace Icinga\Module\Director\Forms;

use Icinga\Module\Director\Web\Form\DirectorObjectForm;

class IcingaServiceGroupForm extends DirectorObjectForm
{
    public function setup()
    {
        $this->addHidden('object_type', 'object');

        $this->addElement('text', 'object_name', array(
            'label'       => $this->translate('Servicegroup'),
            'required'    => true,
            'description' => $this->translate('Icinga object name for this service group')
        ));

        $this->addGroupDisplayNameElement()
             ->setButtons();
    }
}
