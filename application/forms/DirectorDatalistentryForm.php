<?php

namespace Icinga\Module\Director\Forms;

use Icinga\Module\Director\Objects\DirectorDatalist;
use Icinga\Module\Director\Web\Form\DirectorObjectForm;

class DirectorDatalistEntryForm extends DirectorObjectForm
{
    protected $datalist;

    public function setup()
    {
        $this->addElement('text', 'entry_name', array(
            'label'       => $this->translate('Key'),
            'required'    => true,
            'description' => $this->translate(
                'Will be stored as a custom variable value when this entry'
                . ' is chosen from the list'
            )
        ));

        $this->addElement('text', 'entry_value', array(
            'label'       => $this->translate('Value'),
            'required'    => true,
            'description' => $this->translate(
                'This will be the visible caption for this entry'
            )
        ));

        $this->addHidden('list_id', $this->datalist->id);
        $this->addHidden('format', 'string');
        if (!$this->isNew()) {
            $this->addHidden('entry_name', $this->object->entry_name);
        }

        $this->addSimpleDisplayGroup(array('entry_name', 'entry_value'), 'entry', array(
            'legend' => $this->isNew()
                ? $this->translate('Add data list entry')
                : $this->translate('Modify data list entry')
        ));

        $this->setButtons();
    }

    public function setList(DirectorDatalist $list)
    {
        $this->datalist = $list;
        return $this;
    }
}
