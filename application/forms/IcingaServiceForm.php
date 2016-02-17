<?php

namespace Icinga\Module\Director\Forms;

use Icinga\Module\Director\Web\Form\DirectorObjectForm;

class IcingaServiceForm extends DirectorObjectForm
{
    public function setup()
    {
        $this->addObjectTypeElement();

        $this->addElement('text', 'object_name', array(
            'label'       => $this->translate('Name'),
            'required'    => true,
            'description' => $this->translate('Name for the Icinga object you are going to create')
        ));

        /*
        $this->addElement('text', 'groups', array(
            'label' => $this->translate('Servicegroups'),
            'description' => $this->translate('One or more comma separated servicegroup names')
        ));
        */
        $this->optionalBoolean(
            'use_agent',
            $this->translate('Run on agent'),
            $this->translate('Whether the check commmand for this service should be executed on the Icinga agent')
        );
        $this->addZoneElement();
        $this->addImportsElement();
        $this->addDisabledElement();
        $this->addCheckCommandElements();


        if ($this->isTemplate()) {
            $this->addCheckExecutionElements();
        }

        $this->setButtons();
    }
}
