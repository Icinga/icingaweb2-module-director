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
            'description'  => $this->translate('Choose an object type'),
            'required'     => true,
            'multiOptions' => $this->optionalEnum($availableTypes)
        ));

        $this->addElement('select', 'update_policy', array(
            'label'        => $this->translate('Update Policity'),
            'description'  => $this->translate(
                'Define what should happen when an object with a matching key'
                . " already exists. You could merge it's properties (import source"
                . ' wins), replace it completely with the imported object or ignore'
                . ' it (helpful for one-time imports)'
            ),
            'required'     => true,
            'multiOptions' => $this->optionalEnum(array(
                'merge'    => $this->translate('Merge'),
                'override' => $this->translate('Replace'),
                'ignore'   => $this->translate('Ignore'),
            ))
        ));

        $this->addElement('select', 'purge_existing', array(
            'label' => $this->translate('Purge'),
            'description'  => $this->translate(
                'Whether to purge existing objects. This means that objects of'
                . ' the same type will be removed from Director in case they no'
                . ' longer exist at your import source.'
            ),
            'required'     => true,
            'multiOptions' => $this->optionalEnum(array(
                'y' => $this->translate('Yes'),
                'n' => $this->translate('No')
            ))
        ));

        $this->addElement('text', 'filter_expression', array(
            'label'       => $this->translate('Filter Expression'),
            'description' => sprintf(
                $this->translate(
                    'Sync only part of your imported objects with this rule. Icinga Web 2'
                    . ' filter syntax is allowed, so this could look as follows: %s'
                ),
                '(host=a|host=b)&!ip=127.*'
            ),
        ));

        $this->setButtons();
    }
}
