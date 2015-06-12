<?php

namespace Icinga\Module\Director\Forms;

use Icinga\Module\Director\Web\Form\DirectorObjectForm;

class IcingaServiceGroupMemberForm extends DirectorObjectForm
{
    public function setup()
    {
        $this->addElement('select', 'servicegroup_id', array(
            'label' => $this->translate('Servicegroup'),
            'description' => $this->translate('The name of the servicegroup')
        ));

        $this->addElement('select', 'service_id', array(
            'label' => $this->translate('Service'),
            'description' => $this->translate('The name of the service')
        ));

        $this->addElement('submit', $this->translate('Store'));
    }
}
