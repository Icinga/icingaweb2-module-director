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
	    'description' => $this->translate('Please provide a rule name'),
            'required'    => true,
        ));

        $this->addElement('select', 'object_type', array(
            'label' => $this->translate('Object Type'),
            'description' => $this->translate('Choose a object type'),
            'required'    => true,
            'multiOptions' => array( 
                        'null'     => '- please choose -',
                        'host'     => 'host',
                        'user'     => 'user'
            )
        ));

        $this->addElement('select', 'update_policy', array(
            'label' => $this->translate('Update Policity'),
            'description' => $this->translate('Whether the field should be merged, overriden or ignored'),
            'required'    => true,
            'multiOptions' => array( 
                'null'     => '- please choose -',
                'merge'    => 'merge',
		'override' => 'override',
                'ignore'   => 'ignore'
            )
        ));

        $this->addElement('select', 'purge_existing', array(
            'label' => $this->translate('Purge'),
            'description' => $this->translate('Purge existing values.'),
            'required'    => true,
            'multiOptions' => array( 
                'null' => '- please choose -',
                'y'    => 'yes',
                'n'    => 'no'
            )
        ));

        $this->addElement('text', 'filter_expression', array(
            'label' => $this->translate('Filter Expression'),
            'description' => $this->translate('This allows to filter for specific parts.'),
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
