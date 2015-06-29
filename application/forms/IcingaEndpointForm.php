<?php

namespace Icinga\Module\Director\Forms;

use Icinga\Module\Director\Web\Form\DirectorObjectForm;

class IcingaEndpointForm extends DirectorObjectForm
{
    public function setup()
    {
        $isTemplate = isset($_POST['object_type']) && $_POST['object_type'] === 'template';
        $this->addElement('select', 'object_type', array(
            'label' => $this->translate('Object type'),
            'description' => $this->translate('Whether this should be a template'),
            'multiOptions' => array(
                null => '- please choose -',
                'object' => 'Endpoint object',
                'template' => 'Endpoint template',
            )
        ));

        if ($isTemplate) {
            $this->addElement('text', 'object_name', array(
                'label'       => $this->translate('Endpoint template name'),
                'required'    => true,
                'description' => $this->translate('Name for the Icinga endpoint template you are going to create')
            ));
        } else {
            $this->addElement('text', 'object_name', array(
                'label'       => $this->translate('Endpoint'),
                'required'    => true,
                'description' => $this->translate('Name for the Icinga endpoint you are going to create')
            ));
        }

        $this->addElement('text', 'address', array(
            'label' => $this->translate('Endpoint address'),
            'description' => $this->translate('IP address / hostname of remote node')
        ));

        $this->addElement('text', 'port', array(
            'label' => $this->translate('Port'),
            'description' => $this->translate('The port of the endpoint.'),
        ));

        $this->addElement('text', 'log_duration', array(
            'label' => $this->translate('Log Duration'),
            'description' => $this->translate('The log duration time.')
        ));

        $this->addElement('select', 'zone_id', array(
            'label'       => $this->translate('Cluster Zone'),
            'description' => $this->translate('Check this host in this specific Icinga cluster zone'),
            'required'    => true
        ));

        $this->addElement('text', 'imports', array(
            'label' => $this->translate('Imports'),
            'description' => $this->translate('The inherited endpoint template names')
        ));
    }
}
