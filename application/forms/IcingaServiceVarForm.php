<?php

namespace Icinga\Module\Director\Forms;

use Icinga\Module\Director\Web\Form\DirectorObjectForm;

class IcingaServiceVarForm extends DirectorObjectForm
{
    public function setup()
    {
        $this->addElement('select', 'service_id', array(
            'label'       => $this->translate('Service'),
            'description' => $this->translate('The name of the service'),
            'required'    => true
        ));

        $this->addElement('text', 'varname', array(
            'label' => $this->translate('Name'),
            'description' => $this->translate('service var name')
        ));

        $this->addElement('textarea', 'varvalue', array(
            'label' => $this->translate('Value'),
            'description' => $this->translate('service var value')
        ));

        $this->addElement('text', 'format', array(
            'label' => $this->translate('Format'),
            'description' => $this->translate('value format')
        ));

        $this->addElement('submit', $this->translate('Store'));
    }
}
