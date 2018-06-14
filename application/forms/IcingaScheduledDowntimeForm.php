<?php

namespace Icinga\Module\Director\Forms;

use Icinga\Module\Director\Web\Form\DirectorObjectForm;

class IcingaScheduledDowntimeForm extends DirectorObjectForm
{
    public function setup()
    {
        $isTemplate = isset($_POST['object_type']) && $_POST['object_type'] === 'template';
        $this->addElement('select', 'object_type', array(
            'label'       => $this->translate('Object type'),
            'description' => $this->translate('Whether this should be a template'),
            'class'       => 'autosubmit',
            'multiOptions' => $this->optionalEnum(array(
                'object'   => $this->translate('Object'),
                'template' => $this->translate('Template'),
            ))
        ));

        if ($isTemplate) {
            $this->addElement('text', 'object_name', array(
                'label'       => $this->translate('Template name'),
                'required'    => true,
            ));
        } else {
            $this->addElement('text', 'object_name', array(
                'label'       => $this->translate('Downtime name'),
                'required'    => true,
            ));
        }

        $this->addElement('text', 'display_name', array(
            'label' => $this->translate('Display Name'),
            'description' => $this->translate('the display name')
        ));

        $this->addImportsElement();

        $this->setButtons();
    }
}
