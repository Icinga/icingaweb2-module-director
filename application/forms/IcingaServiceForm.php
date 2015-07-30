<?php

namespace Icinga\Module\Director\Forms;

use Icinga\Module\Director\Web\Form\DirectorObjectForm;

class IcingaServiceForm extends DirectorObjectForm
{
    public function setup()
    {
        $isTemplate = isset($_POST['object_type']) && $_POST['object_type'] === 'template';
        $this->addElement('select', 'object_type', array(
            'label' => $this->translate('Object type'),
            'description' => $this->translate('Whether this should be a template'),
            'multiOptions' => array(
                null => '- please choose -',
                'object' => 'Service object',
                'template' => 'Service template',
            ),
            'class' => 'autosubmit'
        ));

        if ($isTemplate) {
            $this->addElement('text', 'object_name', array(
                'label'       => $this->translate('Service template name'),
                'required'    => true,
                'description' => $this->translate('Name for the Icinga service template you are going to create')
            ));
        } else {
            $this->addElement('text', 'object_name', array(
                'label'       => $this->translate('Servicename'),
                'required'    => true,
                'description' => $this->translate('Servicename for the Icinga service you are going to create')
            ));
        }

        $this->addElement('text', 'groups', array(
            'label' => $this->translate('Servicegroups'),
            'description' => $this->translate('One or more comma separated servicegroup names')
        ));

        $this->addCheckCommandElement()
            ->addCheckFlagElements()
            ->addImportsElement();
    }
}
