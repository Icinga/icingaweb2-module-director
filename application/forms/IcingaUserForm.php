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

        $this->addElement('extensibleSet', 'groups', array(
            'label'        => $this->translate('Groups'),
            'multiOptions' => $this->optionallyAddFromEnum($this->enumUsergroups()),
            'positional'   => false,
            'description'  => $this->translate(
                'User groups that should be directly assigned to this user. Groups can be useful'
                . ' for various reasons. You might prefer to send notifications to groups instead of'
                . ' single users'
            )
        ));

        $this->optionalBoolean(
            'enable_notifications',
            $this->translate('Send notifications'),
            $this->translate('Whether to send notifications for this user')
        );

        $this->addElement('extensibleSet', 'states', array(
            'label' => $this->translate('States'),
            'multiOptions' => $this->optionallyAddFromEnum($this->enumStates()),
            'description'  => $this->translate('The host/service states you want to get notifications for'),
        ));

        $this->addElement('extensibleSet', 'types', array(
            'label' => $this->translate('Event types'),
            'multiOptions' => $this->optionallyAddFromEnum($this->enumTypes()),
            'description'  => $this->translate('The event types you want to get notifications for'),
        ));

        $this->addImportsElement();
        $this->addDisabledElement();
        $this->setButtons();
    }

    protected function enumStates()
    {
        $set = new \Icinga\Module\Director\IcingaConfig\StateFilterSet();
        return $set->enumAllowedValues();
    }

    protected function enumTypes()
    {
        $set = new \Icinga\Module\Director\IcingaConfig\TypeFilterSet();
        return $set->enumAllowedValues();
    }

    protected function enumUsergroups()
    {
        $db = $this->db->getDbAdapter();
        $select = $db->select()->from(
            'icinga_usergroup',
            array(
                'name'    => 'object_name',
                'display' => 'COALESCE(display_name, object_name)'
            )
        )->where('object_type = ?', 'object')->order('display');

        return $db->fetchPairs($select);
    }
}
