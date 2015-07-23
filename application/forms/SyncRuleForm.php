<?php

namespace Icinga\Module\Director\Forms;

use Icinga\Module\Director\Web\Form\DirectorObjectForm;
use Icinga\Web\Hook;

class SyncRuleForm extends DirectorObjectForm
{
    public function setup()
    {
        $this->addElement('text', 'rule_name', array(
            'label' => $this->translate('Rule name'),
            'required'    => true,
        ));

        $this->addElement('select', 'object_type', array(
            'label' => $this->translate('Please select a object type'),
            'description' => $this->translate('This must be a column from the source'),
            'required'    => true,
            'multiOptions' => array( 
                        'null'     => '- please choose -',
                        'host'     => 'host',
                        'user'     => 'user'
            )
        ));

        $this->addElement('select', 'update_policy', array(
            'label' => $this->translate('Destination field'),
            'description' => $this->translate('The value of the source will be transformed to the given attribute'),
            'required'    => true,
            'multiOptions' => array( 
                'null'     => '- please choose -',
                'merge'    => 'merge',
		'override' => 'override',
                'ignore'   => 'ignore'
            )
        ));

        $this->addElement('text', 'purge_existing', array(
            'label' => $this->translate('Priority'),
            'description' => $this->translate('This allows to prioritize the import of a field, synced from different sources for the same object'),
            'required'    => true,
            'multiOptions' => array( 
                'null' => '- please choose -',
                'y'    => 'yes',
                'n'    => 'no'
            )
        ));

        $this->addElement('text', 'filter', array(
            'label' => $this->translate('Filter Expression'),
            'description' => $this->translate('This allows to filter for specific parts within the given source field'),
            'required'    => true,
        ));
    }

    public function loadObject($id)
    {
        parent::loadObject($id);
        return $this;
    }

    public function onSuccess()
    {
/*
        $this->getElement('owner')->setValue(
            self::username()
        );
*/
        parent::onSuccess();
    }
}
