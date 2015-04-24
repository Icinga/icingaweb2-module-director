<?php

namespace Icinga\Module\Director\Forms;

use Icinga\Module\Director\Web\Form\DirectorObjectForm;

class IcingaHostForm extends DirectorObjectForm
{
    public function setup()
    {
        $isTemplate = isset($_POST['object_type']) && $_POST['object_type'] === 'template';
        $this->addElement('select', 'object_type', array(
            'label' => $this->translate('Object type'),
            'description' => $this->translate('Whether this should be a template'),
            'multiOptions' => array(
                null => '- please choose -',
                'object' => 'Host object',
                'template' => 'Host template',
            ),
            'class' => 'autosubmit'
        ));

        if ($isTemplate) {
            $this->addElement('text', 'object_name', array(
                'label'       => $this->translate('Host template name'),
                'required'    => true,
                'description' => $this->translate('Name for the Icinga host template you are going to create')
            ));
        } else {
            $this->addElement('text', 'object_name', array(
                'label'       => $this->translate('Hostname'),
                'required'    => true,
                'description' => $this->translate('Hostname for the Icinga host you are going to create')
            ));
        }

        $this->addElement('text', 'address', array(
            'label' => $this->translate('Host address'),
            'description' => $this->translate('Host address. Usually an IPv4 address, but may be any kind of address your check plugin is able to deal with')
        ));

        $this->addElement('text', 'address6', array(
            'label' => $this->translate('IPv6 address'),
            'description' => $this->translate('Usually your hosts main IPv6 address')
        ));

        $this->addElement('select', 'check_command_id', array(
            'label' => $this->translate('Check command'),
            'description' => $this->translate('Check command definition')
        ));

        $this->optionalBoolean(
            'enable_notifications',
            $this->translate('Send notifications'),
            $this->translate('Whether to send notifications for this host')
        );

        $this->optionalBoolean(
            'enable_active_checks', 
            $this->translate('Execute active checks'),
            $this->translate('Whether to actively check this host')
        );

        $this->optionalBoolean(
            'enable_passive_checks', 
            $this->translate('Accept passive checks'),
            $this->translate('Whether to accept passive check results for this host')
        );

        $this->optionalBoolean(
            'enable_event_handler', 
            $this->translate('Enable event handler'),
            $this->translate('Whether to enable event handlers this host')
        );

        $this->optionalBoolean(
            'enable_perfdata',
            $this->translate('Process performance data'),
            $this->translate('Whether to process performance data provided by this host')
        );

        $this->optionalBoolean(
            'volatile',
            $this->translate('Volatile'),
            $this->translate('Whether this check is volatile.')
        );

        $this->addElement('select', 'zone_id', array(
            'label' => $this->translate('Cluster Zone'),
            'description' => $this->translate('Check this host in this specific Icinga cluster zone')
        ));

        $this->addElement('submit', $this->translate('Store'));
    }
}
