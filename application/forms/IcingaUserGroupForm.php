<?php

namespace Icinga\Module\Director\Forms;

use Icinga\Module\Director\Web\Form\DirectorObjectForm;

class IcingaUserGroupForm extends DirectorObjectForm
{
    public function setup()
    {
        $this->addHidden('object_type', 'object');

        $this->addElement('text', 'object_name', array(
            'label'       => $this->translate('Usergroup'),
            'required'    => true,
            'description' => $this->translate('Icinga object name for this user group')
        ));

        $this->addGroupDisplayNameElement()
             ->addZoneElements()
             ->groupMainProperties()
             ->setButtons();
    }

    protected function addZoneElements()
    {
        $this->addZoneElement();
        $this->addDisplayGroup(array('zone_id'), 'clustering', array(
            'decorators' => array(
                'FormElements',
                array('HtmlTag', array('tag' => 'dl')),
                'Fieldset',
            ),
            'order' => 80,
            'legend' => $this->translate('Zone settings')
        ));

        return $this;
    }
}
