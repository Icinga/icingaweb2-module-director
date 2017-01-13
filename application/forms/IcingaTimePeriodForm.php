<?php

namespace Icinga\Module\Director\Forms;

use Icinga\Module\Director\Web\Form\DirectorObjectForm;

class IcingaTimePeriodForm extends DirectorObjectForm
{
    public function setup()
    {
        $isTemplate = isset($_POST['object_type']) && $_POST['object_type'] === 'template';
        $this->addElement('select', 'object_type', array(
            'label'       => $this->translate('Object type'),
            'description' => $this->translate('Whether this should be a template'),
            'class'       => 'autosubmit',
            'multiOptions' => $this->optionalEnum(array(
                'object'   => $this->translate('Timeperiod object'),
                'template' => $this->translate('Timeperiod template'),
            ))
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

        if ($this->isTemplate()) {
            $this->addElement('text', 'update_method', array(
                'label'       => $this->translate('Update Method'),
                'description' => $this->translate('the update method'),
                'value'       => 'LegacyTimePeriod',
            ));
        } else {
            // TODO: I'd like to skip this for objects inheriting from a template
            //       with a defined update_method. However, unfortunately it's too
            //       early for $this->object()->getResolvedProperty('update_method').
            //       Should be fixed.
            $this->addHidden('update_method', 'LegacyTimePeriod');
        }

        $this->addImportsElement();

        $this->setButtons();
    }
}
