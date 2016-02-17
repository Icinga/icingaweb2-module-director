<?php

namespace Icinga\Module\Director\Forms;

use Icinga\Module\Director\Web\Form\DirectorObjectForm;

class SyncRuleForm extends DirectorObjectForm
{
    public function setup()
    {
        $availableTypes = array( 
            'command'          => $this->translate('Command'),
            'endpoint'         => $this->translate('Endpoint'),
            'host'             => $this->translate('Host'),
            'service'          => $this->translate('Service'),
            'user'             => $this->translate('User'),
            'hostgroup'        => $this->translate('Hostgroup'),
            'servicegroup'     => $this->translate('Servicegroup'),
            'usergroup'        => $this->translate('Usergroup'),
            'datalistEntry'    => $this->translate('Datalist entry'),
            'zone'             => $this->translate('Zone'),
        );
        asort($availableTypes);

        $this->addElement('text', 'rule_name', array(
            'label'       => $this->translate('Rule name'),
            'description' => $this->translate('Please provide a rule name'),
            'required'    => true,
        ));

        $this->addElement('select', 'object_type', array(
            'label'        => $this->translate('Object Type'),
            'description'  => $this->translate('Choose a object type'),
            'required'     => true,
            'multiOptions' => $this->optionalEnum($availableTypes)
        ));

        $this->addElement('select', 'update_policy', array(
            'label'        => $this->translate('Update Policity'),
            'description'  => $this->translate('Whether the field should be merged, replaced or ignored'),
            'required'     => true,
            'multiOptions' => $this->optionalEnum(array( 
                'merge'    => 'merge',
                'override' => 'replace',
                'ignore'   => 'ignore'
            ))
        ));

        $this->addElement('select', 'purge_existing', array(
            'label' => $this->translate('Purge'),
            'description' => $this->translate('Purge existing values.'),
            'required'    => true,
            'multiOptions' => $this->optionalEnum(array( 
                'y' => 'yes',
                'n' => 'no'
            ))
        ));

        $this->addElement('text', 'filter_expression', array(
            'label'       => $this->translate('Filter Expression'),
            'description' => $this->translate('This allows to filter for specific parts'),
        ));

        $this->setButtons();
    }
}
