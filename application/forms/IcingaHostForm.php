<?php

namespace Icinga\Module\Director\Forms;

use Icinga\Module\Director\Objects\DirectorDatafield;
use Icinga\Module\Director\Web\Form\DirectorObjectForm;

class IcingaHostForm extends DirectorObjectForm
{
    public function setup()
    {
        $this->addElement('text', 'object_name', array(
            'label'       => $this->translate('Hostname'),
            'required'    => true,
            'description' => $this->translate('Icinga object name for this host')
        ));
 
        $this->addElement('text', 'display_name', array(
            'label' => $this->translate('Display name'),
            'description' => $this->translate('Alternative name for this host')
        ));

        $this->addElement('text', 'address', array(
            'label' => $this->translate('Host address'),
            'description' => $this->translate('Host address. Usually an IPv4 address, but may be any kind of address your check plugin is able to deal with')
        ));

        $this->addElement('text', 'address6', array(
            'label' => $this->translate('IPv6 address'),
            'description' => $this->translate('Usually your hosts main IPv6 address')
        ));

        $this->addZoneElement();
        $this->addImportsElement();

        /*
        $this->addElement('text', 'groups', array(
            'label' => $this->translate('Hostgroups'),
            'description' => $this->translate('One or more comma separated hostgroup names')
        ));
        */

        $elements = array(
            'object_name',
            'display_name',
            'address',
            'address6',
            'zone_id',
            'imports',
        );
        $this->addDisplayGroup($elements, 'object_definition', array(
            'decorators' => array(
                'FormElements',
                'Fieldset',
            ),
            'order' => 20,
            'legend' => $this->translate('Host properties')
        ));

        if ($this->isTemplate()) {
            $this->addCheckCommandElements();
            $this->addCheckExecutionElements();
        } else {
            $this->getElement('imports')->setRequired();
        }

        $this->setButtons();
    }
}
