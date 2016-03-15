<?php

namespace Icinga\Module\Director\Forms;

use Icinga\Module\Director\Web\Form\DirectorObjectForm;

class IcingaZoneForm extends DirectorObjectForm
{
    public function setup()
    {
        $this->addObjectTypeElement();

        $this->addElement('text', 'object_name', array(
            'label'       => $this->translate('Zone (template) name'),
            'required'    => true,
            'description' => $this->translate('Name for the Icinga zone (templat) you are going to create')
        ));

        $this->addElement('select', 'is_global', array(
            'label'        => 'Global zone',
            'description'  => 'Whether this zone should be available everywhere',
            'multiOptions' => array(
                'n'  => $this->translate('No'),
                'y'  => $this->translate('Yes'),
            ),
            'required'     => true,
        ));

        $this->addElement('select', 'parent_id', array(
            'label'        => $this->translate('Parent Zone'),
            'description'  => $this->translate('Chose an (optional) parent zone'),
            'multiOptions' => $this->optionalEnum($this->db->enumZones())
        ));

        // $this->addImportsElement();
        $this->setButtons();
    }
}
