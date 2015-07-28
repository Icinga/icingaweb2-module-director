<?php

namespace Icinga\Module\Director\Forms;

use Icinga\Module\Director\Web\Form\DirectorObjectForm;
use Icinga\Web\Hook;

class SyncPropertyForm extends DirectorObjectForm
{
    public function setup()
    {
        $this->addElement('select', 'rule_id', array(
            'label'        => $this->translate('Rule Name'),
            'multiOptions' => $this->optionalEnum($this->db->enumSyncRule()),
            'required'     => true,
        ));

        $this->addElement('select', 'source_id', array(
            'label'        => $this->translate('Source Name'),
            'multiOptions' => $this->optionalEnum($this->db->enumImportSource()),
            'required'     => true,
        ));

       $this->addElement('text', 'source_expression', array(
            'label'    => $this->translate('Source Expression'),
            'required' => true,
        ));

        $this->addElement('text', 'destination_field', array(
            'label'    => $this->translate('Destination Field'),
            'required' => true,
        ));

        $this->addElement('text', 'priority', array(
            'label'       => $this->translate('Priority'),
            'description' => $this->translate('Priority for the specified source expression'),
            'required'    => true,
        ));

        $this->addElement('text', 'filter_expression', array(
            'label'       => $this->translate('Filter Expression'),
            'description' => $this->translate('This allows to filter for specific parts within the given source expression'),
            'required'    => false,
        ));
    
        $this->addElement('select', 'merge_policy', array(
            'label'        => $this->translate('Merge Policy'),
            'description'  => $this->translate('Whether you want to merge or override the destination field'),
            'required'     => true,
            'multiOptions' => array(
                'null'     => '- please choose -',
                'merge'    => 'merge',
                'override' => 'override'
            )
        ));

    }
}
