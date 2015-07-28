<?php

namespace Icinga\Module\Director\Forms;

use Icinga\Module\Director\Web\Form\DirectorObjectForm;

class IcingaServiceFieldForm extends DirectorObjectForm
{
    public function setup()
    {
        $this->addElement('select', 'service_id', array(
            'label' => 'Service Tpl',
            'description'   => 'Service Template',
            'multiOptions'  => $this->optionalEnum($this->getDb()->enumServiceTemplates())
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
