<?php

namespace Icinga\Module\Director\Forms;

use Icinga\Module\Director\Web\Form\DirectorObjectForm;

class IcingaNotificationForm extends DirectorObjectForm
{
    public function setup()
    {
        $this->addObjectTypeElement();
        if (! $this->hasObjectType()) {
            $this->groupMainProperties();
            return;
        }

        $this->addElement('text', 'object_name', array(
            'label'       => $this->translate('Notification'),
            'required'    => true,
            'description' => $this->translate('Icinga object name for this notification')
        ));
 
        $this->addDisabledElement()
             ->addImportsElement()
             ->addUsersElement()
             ->addUsergroupsElement()
             ->addIntervalElement()
             ->addPeriodElement()
             ->addTimesElements()
             ->addDisabledElement()
             ->addCommandElements()
             ->addEventFilterElements()
             ->groupMainProperties()
             ->setButtons();
    }

    protected function addUsersElement()
    {
        $users = $this->enumUsers();
        if (empty($users)) {
            return $this;
        }

        $this->addElement(
            'extensibleSet',
            'users',
            array(
                'label'       => $this->translate('Users'),
                'description' => $this->translate(
                    'Users that should be notified by this notifications'
                ),
                'multiOptions' => $this->optionalEnum($users)
            )
        );

        return $this;
    }

    protected function addUsergroupsElement()
    {
        $groups = $this->enumUsergroups();
        if (empty($groups)) {
            return $this;
        }

        $this->addElement(
            'extensibleSet',
            'user_groups',
            array(
                'label'       => $this->translate('User groups'),
                'description' => $this->translate(
                    'User groups that should be notified by this notifications'
                ),
                'multiOptions' => $this->optionalEnum($groups)
            )
        );

        return $this;
    }

    protected function addIntervalElement()
    {
        $this->addElement(
            'text',
            'notification_interval',
            array(
                'label' => $this->translate('Notification interval'),
                'description' => $this->translate(
                    'The notification interval (in seconds). This interval is'
                    . ' used for active notifications. Defaults to 30 minutes.'
                    . ' If set to 0, re-notifications are disabled.'
                )
            )
        );

        return $this;
    }

    protected function addTimesElements()
    {
        $this->addElement(
            'text',
            'times_begin',
            array(
                'label' => $this->translate('First notification delay'),
                'description' => $this->translate(
                    'Delay unless the first notification should be sent'
                )
            )
        );

        $this->addElement(
            'text',
            'times_end',
            array(
                'label' => $this->translate('Last notification'),
                'description' => $this->translate(
                    'When the last notification should be sent'
                )
            )
        );

        return $this;
    }

    protected function addPeriodElement()
    {
        $periods = $this->db->enumTimeperiods();
        if (empty($periods)) {
            return $this;
        }

        $this->addElement(
            'select',
            'period',
            array(
                'label' => $this->translate('Time period'),
                'description' => $this->translate(
                    'The name of a time period which determines when this'
                    . ' notification should be triggered. Not set by default.'
                ),
                'multiOptions' => $this->optionalEnum($periods),
            )
        );

        return $this;
    }

    protected function addCommandElements()
    {
        if (! $this->isTemplate()) {
            return $this;
        }

        $this->addElement('select', 'command_id', array(
            'label' => $this->translate('Notification command'),
            'description'  => $this->translate('Check command definition'),
            'multiOptions' => $this->optionalEnum($this->db->enumNotificationCommands()),
            'class'        => 'autosubmit',
        ));

        return $this;
    }

    protected function enumUsers()
    {
        $db = $this->db->getDbAdapter();
        $select = $db->select()->from(
            'icinga_user',
            array(
                'name'    => 'object_name',
                'display' => 'COALESCE(display_name, object_name)'
            )
        )->where('object_type = ?', 'object')->order('display');

        return $db->fetchPairs($select);
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
