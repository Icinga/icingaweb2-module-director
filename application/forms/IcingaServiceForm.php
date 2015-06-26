<?php

namespace Icinga\Module\Director\Forms;

use Icinga\Module\Director\Web\Form\DirectorObjectForm;

class IcingaServiceForm extends DirectorObjectForm
{
    public function setup()
    {
        $isTemplate = isset($_POST['object_type']) && $_POST['object_type'] === 'template';
        $this->addElement('select', 'object_type', array(
            'label' => $this->translate('Object type'),
            'description' => $this->translate('Whether this should be a template'),
            'multiOptions' => array(
                null => '- please choose -',
                'object' => 'Service object',
                'template' => 'Service template',
            ),
            'class' => 'autosubmit'
        ));

        if ($isTemplate) {
            $this->addElement('text', 'object_name', array(
                'label'       => $this->translate('Service template name'),
                'required'    => true,
                'description' => $this->translate('Name for the Icinga service template you are going to create')
            ));
        } else {
            $this->addElement('text', 'object_name', array(
                'label'       => $this->translate('Servicename'),
                'required'    => true,
                'description' => $this->translate('Servicename for the Icinga service you are going to create')
            ));
        }

        $this->addElement('select', 'check_command_id', array(
            'label' => $this->translate('Check command'),
            'description' => $this->translate('Check command definition')
        ));

        $this->optionalBoolean(
            'enable_notifications',
            $this->translate('Send notifications'),
            $this->translate('Whether to send notifications for this service')
        );

        $this->optionalBoolean(
            'enable_active_checks', 
            $this->translate('Execute active checks'),
            $this->translate('Whether to actively check this service')
        );

        $this->optionalBoolean(
            'enable_passive_checks', 
            $this->translate('Accept passive checks'),
            $this->translate('Whether to accept passive check results for this service')
        );

        $this->optionalBoolean(
            'enable_event_handler',
            $this->translate('Enable event handler'),
            $this->translate('Whether to enable event handlers this service')
        );

        $this->optionalBoolean(
            'enable_perfdata',
            $this->translate('Process performance data'),
            $this->translate('Whether to process performance data provided by this service')
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

        $this->addElement('text', 'groups', array(
            'label' => $this->translate('Servicegroups'),
            'description' => $this->translate('One or more comma separated servicegroup names')
        ));

        $this->addElement('text', 'imports', array(
            'label' => $this->translate('Imports'),
            'description' => $this->translate('The inherited service template names')
        ));
    }
}
