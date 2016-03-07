<?php

namespace Icinga\Module\Director\Forms;

use Icinga\Module\Director\Web\Form\DirectorObjectForm;

class IcingaEndpointForm extends DirectorObjectForm
{
    public function setup()
    {
        $this->addObjectTypeElement();
        if (! $this->hasObjectType()) {
            return;
        }

        if ($this->isTemplate()) {
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

        $this->addElement('text', 'host', array(
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

        $this->addElement('select', 'apiuser_id', array(
            'label'        => $this->translate('API user'),
            'multiOptions' => $this->optionalEnum($this->db->enumApiUsers())
        ));

        $this->addZoneElement()
            ->addImportsElement();

        $this->setButtons();
    }
}
