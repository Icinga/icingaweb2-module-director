<?php

namespace Icinga\Module\Director\Forms;

use Icinga\Module\Director\Web\Form\DirectorObjectForm;

class DirectorDatafieldForm extends DirectorObjectForm
{
    public function setup()
    {
        $this->addElement('text', 'varname', array(
            'label' => $this->translate('Field name')
        ));

        $this->addElement('text', 'caption', array(
            'label' => $this->translate('Caption')
        ));

        $this->addElement('textarea', 'description', array(
            'label' => $this->translate('Description')
        ));

        $this->addElement('text', 'datatype', array(
            'label' => $this->translate('Data type')
        ));

        $this->addElement('text', 'datatype', array(
            'label' => $this->translate('Data type')
        ));

        $this->addElement('text', 'format', array(
            'label' => $this->translate('Format'),
            'description' => $this->translate('Field format')
        ));
    }
}
