<?php

namespace Icinga\Module\Director\Forms;

use Icinga\Module\Director\Web\Form\DirectorObjectForm;

class IcingaHostGroupForm extends DirectorObjectForm
{
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
             ->setButtons();
    }

    protected function addAssignmentElements()
    {
        $this->addAssignFilter([
           'suggestionContext' => 'HostFilterColumns',
            'required' => false,
            'description' => $this->translate(
                'This allows you to configure an assignment filter. Please feel'
                . ' free to combine as many nested operators as you want'
            )
        ]);

        return $this;
    }
}
