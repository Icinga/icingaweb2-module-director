<?php

namespace Icinga\Module\Director\Forms;

use Icinga\Module\Director\Web\Form\DirectorObjectForm;

class IcingaTimePeriodForm extends DirectorObjectForm
{
    public function setup()
    {
        $isTemplate = isset($_POST['object_type']) && $_POST['object_type'] === 'template';
        $this->addElement('select', 'object_type', array(
            'label' => $this->translate('Object type'),
            'description' => $this->translate('Whether this should be a template'),
            'multiOptions' => array(
                null => '- please choose -',
                'object' => 'Timeperiod object',
                'template' => 'Timeperiod template',
            )
        ));

        if ($isTemplate) {
            $this->addElement('text', 'object_name', array(
                'label'       => $this->translate('Timeperiod template name'),
                'required'    => true,
                'description' => $this->translate('Name for the Icinga timperiod template you are going to create')
            ));
        } else {
            $this->addElement('text', 'object_name', array(
                'label'       => $this->translate('Timeperiod'),
                'required'    => true,
                'description' => $this->translate('Name for the Icinga timeperiod you are going to create')
            ));
        }

        $this->addElement('text', 'display_name', array(
            'label' => $this->translate('Display Name'),
            'description' => $this->translate('the display name')
        ));

        $this->addElement('text', 'update_method', array(
            'label' => $this->translate('Update Method'),
            'description' => $this->translate('the update method'),
        ));

        $this->addZoneElement();
        $this->addImportsElement();
    }
}
