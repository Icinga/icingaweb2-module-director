<?php

namespace Icinga\Module\Director\Forms;

use Icinga\Module\Director\Web\Form\DirectorObjectForm;

class IcingaZoneForm extends DirectorObjectForm
{
    public function setup()
    {
        $this->addHidden('object_type', 'object');

        $this->addElement('text', 'object_name', array(
            'label'       => $this->translate('Zone name'),
            'required'    => true,
            'description' => $this->translate(
                'Name for the Icinga zone you are going to create'
            )
        ));

        $this->addElement('select', 'is_global', array(
            'label'        => $this->translate('Global zone'),
            'description'  => $this->translate(
                'Whether this zone should be available everywhere. Please note that'
                . ' it rarely leads to the desired result when you try to distribute'
                . ' global zones in distrubuted environments'
            ),
            'multiOptions' => array(
                'n'  => $this->translate('No'),
                'y'  => $this->translate('Yes'),
            ),
            'required'     => true,
        ));

        $this->addElement('select', 'parent_id', array(
            'label'        => $this->translate('Parent Zone'),
            'description'  => $this->translate('Chose an (optional) parent zone'),
            'multiOptions' => $this->optionalEnum($this->db->enumZones()),
        ));

        $this->setButtons();
    }
}
