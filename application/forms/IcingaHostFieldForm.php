<?php

namespace Icinga\Module\Director\Forms;

use Icinga\Module\Director\Web\Form\DirectorObjectForm;

class IcingaHostFieldForm extends DirectorObjectForm
{
    public function setup()
    {
        $this->addElement('select', 'host_id', array(
            'label' => 'Host Tpl',
            'description'   => 'Host Template',
            'multiOptions'  => $this->optionalEnum($this->getDb()->enumHostTemplates())
        ));

        $this->addElement('select', 'datafield_id', array(
            'label'         => 'Field',
            'description'   => 'Field to assign',
            'multiOptions'  => $this->optionalEnum($this->getDb()->enumDatafields())
        ));

        $this->optionalBoolean(
            'is_required',
            $this->translate('Required'),
            $this->translate('Whether this filed is required or not.')
        );
    }
}
