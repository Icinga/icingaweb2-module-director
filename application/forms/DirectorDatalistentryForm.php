<?php

namespace Icinga\Module\Director\Forms;

use Icinga\Module\Director\Web\Form\DirectorObjectForm;

class DirectorDatalistEntryForm extends DirectorObjectForm
{
    public function setup()
    {
        $this->addElement('text', 'entry_name', array(
            'label' => 'Name'
        ));
        $this->addElement('text', 'entry_value', array(
            'label' => 'Value'
        ));
        $this->addElement('select', 'format', array(
            'label'        => 'Type',
            'multiOptions' => array('string' => $this->translate('String'))
        ));

        $this->addElement('hidden', 'list_id', array(
            'value' => $this->getRequest()->getParam('list_id'),
        ));
    }
}
