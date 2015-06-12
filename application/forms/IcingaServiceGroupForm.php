<?php

namespace Icinga\Module\Director\Forms;

use Icinga\Module\Director\Web\Form\DirectorObjectForm;

class IcingaServiceGroupForm extends DirectorObjectForm
{
    public function setup()
    {
        $isTemplate = isset($_POST['object_type']) && $_POST['object_type'] === 'template';
        $this->addElement('select', 'object_type', array(
            'label' => $this->translate('Object type'),
            'description' => $this->translate('Whether this should be a template'),
            'multiOptions' => array(
                null => '- please choose -',
                'object' => 'Servicegroup object',
                'template' => 'Servicegroup template',
            )
        ));

        if ($isTemplate) {
            $this->addElement('text', 'object_name', array(
                'label'       => $this->translate('Servicegroup template name'),
                'required'    => true,
                'description' => $this->translate('Servicegroup for the Icinga servicegroup template you are going to create')
            ));
        } else {
            $this->addElement('text', 'object_name', array(
                'label'       => $this->translate('Servicegroup'),
                'required'    => true,
                'description' => $this->translate('Servicegroup for the Icinga servicegroup you are going to create')
            ));
        }

        $this->addElement('text', 'display_name', array(
            'label' => $this->translate('Display Name'),
            'description' => $this->translate('The name which should displayed.')
        ));

        $this->addElement('submit', $this->translate('Store'));
    }
}
