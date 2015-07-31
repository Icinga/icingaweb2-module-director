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

        $this->addElement('text', 'address', array(
            'label' => $this->translate('Host address'),
            'description' => $this->translate('Host address. Usually an IPv4 address, but may be any kind of address your check plugin is able to deal with')
        ));

        $this->addElement('text', 'address6', array(
            'label' => $this->translate('IPv6 address'),
            'description' => $this->translate('Usually your hosts main IPv6 address')
        ));

        $this->addImportsElement();

        /*
        $this->addElement('text', 'groups', array(
            'label' => $this->translate('Hostgroups'),
            'description' => $this->translate('One or more comma separated hostgroup names')
        ));
        */

        if ($this->isTemplate()) {
            $this->addElement('text', 'address', array(
                'label' => $this->translate('Host address'),
                'description' => $this->translate('Host address. Usually an IPv4 address, but may be any kind of address your check plugin is able to deal with')
            ));

            $this->addElement('text', 'address6', array(
                'label' => $this->translate('IPv6 address'),
                'description' => $this->translate('Usually your hosts main IPv6 address')
            ));

            $this->addCheckExecutionElements();
        } else {
            $this->getElement('imports')->setRequired();
        }

        $this->addZoneElement();
    }
}
