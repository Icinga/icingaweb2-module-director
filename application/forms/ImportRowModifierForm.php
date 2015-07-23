<?php

namespace Icinga\Module\Director\Forms;

use Icinga\Module\Director\Web\Form\DirectorObjectForm;
use Icinga\Web\Hook;

class ImportRowModifierForm extends DirectorObjectForm
{
    public function setup()
    {
        $this->addElement('text', 'source_name', array(
            'label' => $this->translate('Source name'),
            'required'    => true,
        ));

        $this->addElement('text', 'source_field', array(
            'label' => $this->translate('Source field'),
            'description' => $this->translate('This must be a column from the source'),
            'required'    => true,
        ));

	$this->addElement('text', 'destination_field', array(
            'label' => $this->translate('Destination field'),
            'description' => $this->translate('The value of the source will be transformed to the given attribute'),
            'required'    => true,
        ));

	$this->addElement('text', 'priority', array(
	    'label' => $this->translate('Priority'),
	    'description' => $this->translate('This allows to prioritize the import of a field, synced from different sources for the same object'),
	    'required'    => true,
	));

	$this->addElement('text', 'filter', array(
            'label' => $this->translate('Filter Expression'),
            'description' => $this->translate('This allows to filter for specific parts within the given source field'),
            'required'    => true,
        ));
	
        $this->addElement('select', 'merge', array(
            'label'       => $this->translate('Source Type'),
            'required'    => true,
            'multiOptions' => array( 
			'null'     => '- please choose -',
			'merge'	   => 'merge',
			'override' => 'override'
	    )
        ));

    }

}
