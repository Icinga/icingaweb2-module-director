<?php

namespace Icinga\Module\Director\Forms;

use Icinga\Module\Director\Web\Form\DirectorObjectForm;

class IcingaZoneForm extends DirectorObjectForm
{
    public function setup()
    {
        $this->addElement('select', 'object_type', array(
            'label' => $this->translate('Object type'),
            'description' => $this->translate('Whether this should be a template'),
            'multiOptions' => $this->optionalEnum(array(
                'object'   => $this->translate('Zone object'),
                'template' => $this->translate('Zone template'),
            )),
        ));

        $this->addElement('text', 'object_name', array(
            'label'       => $this->translate('Zone (template) name'),
            'required'    => true,
            'description' => $this->translate('Name for the Icinga zone (templat) you are going to create')
        ));

        $this->addElement('select', 'is_global', array(
            'label' => 'Global zone',
            'description' => 'Whether this zone should be available everywhere',
            'multiOptions' => array(
                'n'  => $this->translate('No'),
                'y'  => $this->translate('Yes'),
            ),
            'required' => true,
        ));

        $this->addElement('select', 'parent_zone_id', array(
            'label' => $this->translate('Parent Zone'),
            'description' => $this->translate('Chose an (optional) parent zone')
        ));

        $this->addElement('text', 'imports', array(
            'label' => $this->translate('Imports'),
            'description' => $this->translate('The inherited zone template names')
        ));
    }
}
