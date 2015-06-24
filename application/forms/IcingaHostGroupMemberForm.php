<?php

namespace Icinga\Module\Director\Forms;

use Icinga\Module\Director\Web\Form\DirectorObjectForm;

/**
 * @deprecated
 */
class IcingaHostGroupMemberForm extends DirectorObjectForm
{
    public function setup()
    {
        $this->addElement('select', 'hostgroup_id', array(
            'label' => $this->translate('Hostgroup'),
            'description' => $this->translate('The name of the hostgroup')
        ));

        $this->addElement('select', 'host_id', array(
            'label' => $this->translate('Host'),
            'description' => $this->translate('The name of the host')
        ));

        $this->addElement('submit', $this->translate('Store'));
    }
}
