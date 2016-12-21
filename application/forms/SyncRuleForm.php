<?php

namespace Icinga\Module\Director\Forms;

use Icinga\Module\Director\Web\Form\DirectorObjectForm;

class SyncRuleForm extends DirectorObjectForm
{
    public function setup()
    {
        $availableTypes = array(
            'host'             => $this->translate('Host'),
            'hostgroup'        => $this->translate('Host group'),
            'service'          => $this->translate('Service'),
            'servicegroup'     => $this->translate('Service group'),
            'serviceSet'       => $this->translate('Service Set'),
            'user'             => $this->translate('User'),
            'usergroup'        => $this->translate('User group'),
            'datalistEntry'    => $this->translate('Datalist entry'),
            'command'          => $this->translate('Command'),
            'timePeriod'       => $this->translate('Time period'),
            'endpoint'         => $this->translate('Endpoint'),
            'zone'             => $this->translate('Zone'),
        );

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
            'label'        => $this->translate('Update Policy'),
            'description'  => $this->translate(
                'Define what should happen when an object with a matching key'
                . " already exists. You could merge its properties (import source"
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
