<?php

namespace Icinga\Module\Director\Forms;

use Icinga\Module\Director\Web\Form\DirectorObjectForm;

class SyncRuleForm extends DirectorObjectForm
{
    public function setup()
    {
        $availableTypes = [
            'host'              => $this->translate('Host'),
            'hostgroup'         => $this->translate('Host Group'),
            'service'           => $this->translate('Service'),
            'servicegroup'      => $this->translate('Service Group'),
            'serviceSet'        => $this->translate('Service Set'),
            'user'              => $this->translate('User'),
            'usergroup'         => $this->translate('User Group'),
            'datalistEntry'     => $this->translate('Data List Entry'),
            'command'           => $this->translate('Command'),
            'timePeriod'        => $this->translate('Time Period'),
            'notification'      => $this->translate('Notification'),
            'scheduledDowntime' => $this->translate('Scheduled Downtime'),
            'dependency'        => $this->translate('Dependency'),
            'endpoint'          => $this->translate('Endpoint'),
            'zone'              => $this->translate('Zone'),
        ];

        $this->addElement('text', 'rule_name', [
            'label'       => $this->translate('Rule name'),
            'description' => $this->translate('Please provide a rule name'),
            'required'    => true,
        ]);

        $this->addElement('textarea', 'description', [
            'label'       => $this->translate('Description'),
            'description' => $this->translate(
                'An extended description for this Sync Rule. This should explain'
                . ' what this Rule is going to accomplish.'
            ),
            'rows'        => '3',
        ]);

        $this->addElement('select', 'object_type', [
            'label'        => $this->translate('Object Type'),
            'description'  => $this->translate('Choose an object type'),
            'required'     => true,
            'multiOptions' => $this->optionalEnum($availableTypes)
        ]);

        $this->addElement('select', 'update_policy', [
            'label'        => $this->translate('Update Policy'),
            'description'  => $this->translate(
                'Define what should happen when an object with a matching key'
                . " already exists. You could merge its properties (import source"
                . ' wins), replace it completely with the imported object or ignore'
                . ' it (helpful for one-time imports)'
            ),
            'required'     => true,
            'multiOptions' => $this->optionalEnum([
                'merge'    => $this->translate('Merge'),
                'override' => $this->translate('Replace'),
                'ignore'   => $this->translate('Ignore'),
            ])
        ]);

        $this->addBoolean('purge_existing', [
            'label'       => $this->translate('Purge'),
            'description' => $this->translate(
                'Whether to purge existing objects. This means that objects of'
                . ' the same type will be removed from Director in case they no'
                . ' longer exist at your import source.'
            ),
            'required'   => true,

        ]);

        $this->addElement('text', 'filter_expression', [
            'label'       => $this->translate('Filter Expression'),
            'description' => sprintf(
                $this->translate(
                    'Sync only part of your imported objects with this rule. Icinga Web 2'
                    . ' filter syntax is allowed, so this could look as follows: %s'
                ),
                '(host=a|host=b)&!ip=127.*'
            ) . ' ' . $this->translate(
                'Be careful: this is usually NOT what you want, as it makes Sync "blind"'
                . ' for objects matching this filter. This means that "Purge" will not'
                . ' work as expected. The "Black/Whitelist" Import Property Modifier'
                . ' is probably what you\'re looking for.'
            ),
        ]);

        $this->setButtons();
    }
}
