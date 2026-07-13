<?php

namespace Icinga\Module\Director\Forms;

class IcingaHostGroupForm extends IcingaGroupForm
{
    /**
     * @throws \Zend_Form_Exception
     */
    public function setup()
    {
        $this->addHidden('object_type', 'object');

        $this->addElement('text', 'object_name', [
            'label'       => $this->translate('Hostgroup'),
            'required'    => true,
            'description' => $this->translate('Icinga object name for this host group')
        ]);

        $this->addGroupDisplayNameElement()
             ->addAssignmentElements()
             ->addZoneElements()
             ->setButtons();
    }

    protected function addAssignmentElements()
    {
        $this->addAssignFilter([
           'suggestionContext' => 'HostFilterColumns',
            'required' => false,
            'description' => $this->translate(
                'This allows you to configure an assignment filter. Please feel'
                . ' free to combine as many nested operators as you want. The'
                . ' "contains" operator is valid for arrays only. Please use'
                . ' wildcards and the = (equals) operator when searching for'
                . ' partial string matches, like in *.example.com'
            )
        ]);

        return $this;
    }
}
