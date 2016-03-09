<?php

namespace Icinga\Module\Director\Forms;

use Icinga\Module\Director\Web\Form\DirectorObjectForm;

class IcingaHostGroupForm extends DirectorObjectForm
{
    public function setup()
    {
        $this->addHidden('object_type', 'object');

        $this->addElement('text', 'object_name', array(
            'label'       => $this->translate('Hostgroup'),
            'required'    => true,
            'description' => $this->translate('Icinga object name for this host group')
        ));

        $this->addGroupDisplayNameElement()
             ->setButtons();
    }
}
