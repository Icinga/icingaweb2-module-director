<?php

namespace Icinga\Module\Director\Forms;

use Icinga\Module\Director\Web\Form\DirectorObjectForm;

class DirectorDatalistEntryForm extends DirectorObjectForm
{
    protected $listId;

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
    }

    public function onSuccess()
    {
        $this->object()->list_id = $this->listId;
        parent::onSuccess();
    }

    public function setListId($id)
    {
        $this->listId = $id;
        return $this;
    }
}
