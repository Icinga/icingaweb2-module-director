<?php

namespace Icinga\Module\Director\Forms;

use Icinga\Module\Director\Web\Form\DirectorObjectForm;

class IcingaUserForm extends DirectorObjectForm
{
    /**
     * @throws \Zend_Form_Exception
     */
    public function setup()
    {
        $this->addObjectTypeElement();
        if (! $this->hasObjectType()) {
            $this->groupMainProperties();
            return;
        }

        if ($this->isTemplate()) {
            $this->addElement('text', 'object_name', array(
                'label'       => $this->translate('User template name'),
                'required'    => true,
                'description' => $this->translate('Name for the Icinga user template you are going to create')
            ));
        } else {
            $this->addElement('text', 'object_name', array(
                'label'       => $this->translate('Username'),
                'required'    => true,
                'description' => $this->translate('Name for the Icinga user object you are going to create')
            ));
        }

        if (! $this->isTemplate()) {
            $this->addElement('text', 'email', array(
                'label' => $this->translate('Email'),
                'description' => $this->translate('The Email address of the user.')
            ));

            $this->addElement('text', 'pager', array(
                'label' => $this->translate('Pager'),
                'description' => $this->translate('The pager address of the user.')
            ));
        }

        $this->addGroupsElement()
             ->addImportsElement()
             ->addDisplayNameElement()
             ->addEnableNotificationsElement()
             ->addDisabledElement()
             ->addZoneElements()
             ->addPeriodElement()
             ->addEventFilterElements()
             ->groupMainProperties()
             ->setButtons();
    }

    /**
     * @return $this
     * @throws \Zend_Form_Exception
     */
    protected function addZoneElements()
    {
        if (! $this->isTemplate()) {
            return $this;
        }

        $this->addZoneElement();
        $this->addDisplayGroup(array('zone_id'), 'clustering', array(
            'decorators' => array(
                'FormElements',
                array('HtmlTag', array('tag' => 'dl')),
                'Fieldset',
            ),
            'order' => self::GROUP_ORDER_CLUSTERING,
            'legend' => $this->translate('Zone settings')
        ));

        return $this;
    }

    /**
     * @return $this
     */
    protected function addEnableNotificationsElement()
    {
        $this->optionalBoolean(
            'enable_notifications',
            $this->translate('Send notifications'),
            $this->translate('Whether to send notifications for this user')
        );

        return $this;
    }

    /**
     * @return $this
     * @throws \Zend_Form_Exception
     */
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

    /**
     * @return $this
     * @throws \Zend_Form_Exception
     */
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

    /**
     * @return $this
     * @throws \Zend_Form_Exception
     */
    protected function addPeriodElement()
    {
        $periods = $this->db->enumTimeperiods();
        if (empty($periods)) {
            return $this;
        }

        $this->addElement(
            'select',
            'period_id',
            array(
                'label' => $this->translate('Time period'),
                'description' => $this->translate(
                    'The name of a time period which determines when notifications'
                    . ' to this User should be triggered. Not set by default.'
                ),
                'multiOptions' => $this->optionalEnum($periods),
            )
        );

        return $this;
    }

    /**
     * @throws \Zend_Form_Exception
     */
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
            'period_id',
            'enable_notifications',
            'disabled',
        );
        $this->addDisplayGroup($elements, 'object_definition', array(
            'decorators' => array(
                'FormElements',
                array('HtmlTag', array('tag' => 'dl')),
                'Fieldset',
            ),
            'order' => self::GROUP_ORDER_OBJECT_DEFINITION,
            'legend' => $this->translate('User properties')
        ));
    }

    /**
     * @return array
     */
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
