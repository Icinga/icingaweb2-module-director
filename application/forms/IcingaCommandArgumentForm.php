<?php

namespace Icinga\Module\Director\Forms;

use Icinga\Module\Director\Web\Form\DirectorObjectForm;

class IcingaCommandArgumentForm extends DirectorObjectForm
{
    public function setup()
    {

        $this->addElement('select', 'command_id', array(
            'label' => $this->translate('Check command'),
            'description' => $this->translate('Check command definition')
        ));

        $this->addElement('text', 'argument_name', array(
            'label'       => $this->translate('Argument name'),
            'required'    => true,
            'description' => $this->translate('e.g. -H or --hostname, empty means "skip_key"')
        ));

        $this->addElement('text', 'argument_value', array(
            'label' => $this->translate('Value'),
            'description' => $this->translate('e.g. 5%, $hostname$, $lower$%:$upper$%')
        ));
/*
        $this->optionalBoolean(
            'required',
            $this->translate('Required'),
            $this->translate('Whether this is a mandatory parameter')
        );
*/
        $this->addElement('submit', $this->translate('Store'));
    }
}
