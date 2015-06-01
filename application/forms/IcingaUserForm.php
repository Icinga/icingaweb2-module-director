<?php

namespace Icinga\Module\Director\Forms;

use Icinga\Module\Director\Web\Form\DirectorObjectForm;

class IcingaUserForm extends DirectorObjectForm
{
    public function setup()
    {
        $isTemplate = isset($_POST['object_type']) && $_POST['object_type'] === 'template';
        $this->addElement('select', 'object_type', array(
            'label' => $this->translate('Object type'),
            'description' => $this->translate('Whether this should be a template'),
            'multiOptions' => array(
                null => '- please choose -',
                'object' => 'User object',
                'template' => 'User template',
            )
        ));

        if ($isTemplate) {
            $this->addElement('text', 'object_name', array(
                'label'       => $this->translate('User template name'),
                'required'    => true,
                'description' => $this->translate('User for the Icinga host template you are going to create')
            ));
        } else {
            $this->addElement('text', 'object_name', array(
                'label'       => $this->translate('Username'),
                'required'    => true,
                'description' => $this->translate('Username for the Icinga host you are going to create')
            ));
        }

        $this->addElement('text', 'email', array(
            'label' => $this->translate('Email'),
            'description' => $this->translate('The Email address of the user.')
        ));

        $this->addElement('text', 'pager', array(
            'label' => $this->translate('Pager'),
            'description' => $this->translate('The pager address of the user.')
        ));

        $this->optionalBoolean(
            'enable_notifications',
            $this->translate('Send notifications'),
            $this->translate('Whether to send notifications for this user')
        );


        $this->addElement('select', 'zone_id', array(
            'label' => $this->translate('Cluster Zone'),
            'description' => $this->translate('Check this user in this specific Icinga cluster zone')
        ));

        $this->addElement('submit', $this->translate('Store'));
    }
}
