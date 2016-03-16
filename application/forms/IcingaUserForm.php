<?php

namespace Icinga\Module\Director\Forms;

use Icinga\Module\Director\Web\Form\DirectorObjectForm;

class IcingaUserForm extends DirectorObjectForm
{
    public function setup()
    {
        $this->addObjectTypeElement();
        if (! $this->hasObjectType()) {
            return $this->groupMainProperties();
        }

        if ($this->isTemplate()) {
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

        $this->addGroupsElement()
             ->addImportsElement()
             ->addDisplayNameElement()
             ->addEnableNotificationsElement()
             ->addDisabledElement()
             ->addEventFilterElements()
             ->groupMainProperties()
             ->setButtons();
    }

    protected function addEnableNotificationsElement()
    {
        $this->optionalBoolean(
            'enable_notifications',
            $this->translate('Send notifications'),
            $this->translate('Whether to send notifications for this user')
        );

        return $this;
    }

    protected function addGroupsElement()
    {
        $groups = $this->enumUsergroups();

        if (empty($groups)) {
            return $this;
        }

        $this->addElement('extensibleSet', 'groups', array(
            'label'        => $this->translate('Groups'),
            'multiOptions' => $this->optionallyAddFromEnum($groups),
            'positional'   => false,
            'description'  => $this->translate(
                'User groups that should be directly assigned to this user. Groups can be useful'
                . ' for various reasons. You might prefer to send notifications to groups instead of'
                . ' single users'
            )
        ));

        return $this;
    }

    protected function addDisplayNameElement()
    {
        if ($this->isTemplate()) {
            return $this;
        }

        $this->addElement('text', 'display_name', array(
            'label' => $this->translate('Display name'),
            'description' => $this->translate(
                'Alternative name for this user. In case your object name is a'
                . ' username, this could be the full name of the corresponding person'
            )
        ));

        return $this;
    }

    protected function groupObjectDefinition()
    {
        $elements = array(
            'object_type',
            'object_name',
            'display_name',
            'imports',
            'groups',
            'email',
            'pager',
            'enable_notifications',
            'disabled',
        );
        $this->addDisplayGroup($elements, 'object_definition', array(
            'decorators' => array(
                'FormElements',
                array('HtmlTag', array('tag' => 'dl')),
                'Fieldset',
            ),
            'order' => 20,
            'legend' => $this->translate('User properties')
        ));
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
