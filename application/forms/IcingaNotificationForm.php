<?php

namespace Icinga\Module\Director\Forms;

use Icinga\Module\Director\DataType\DataTypeDirectorObject;
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

        if ($this->isTemplate()) {
            $this->addElement('text', 'object_name', array(
                'label'       => $this->translate('Notification Template'),
                'required'    => true,
                'description' => $this->translate('Name for the Icinga notification template you are going to create')
            ));
        } else {
            $this->addElement('text', 'object_name', array(
                'label'       => $this->translate('Notification'),
                'required'    => true,
                'description' => $this->translate('Name for the Icinga notification you are going to create')
            ));

            $this->eventuallyAddNameRestriction(
                'director/notification/apply/filter-by-name'
            );
        }

        $this->addDisabledElement()
             ->addImportsElement()
             ->addUsersElement()
             ->addUsergroupsElement()
             ->addIntervalElement()
             ->addPeriodElement()
             ->addTimesElements()
             ->addAssignmentElements()
             ->addDisabledElement()
             ->addCommandElements()
             ->addEventFilterElements()
             ->addZoneElements()
             ->groupMainProperties()
             ->setButtons();
    }

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
     * @return self
     */
    protected function addAssignmentElements()
    {
        if (!$this->object || !$this->object->isApplyRule()) {
            return $this;
        }

        $this->addElement('select', 'apply_to', array(
            'label'        => $this->translate('Apply to'),
            'description'  => $this->translate(
                'Whether this notification should affect hosts or services'
            ),
            'required'     => true,
            'class'        => 'autosubmit',
            'multiOptions' => $this->optionalEnum(
                array(
                    'host'    => $this->translate('Hosts'),
                    'service' => $this->translate('Services'),
                )
            )
        ));

        $applyTo = $this->getSentOrObjectValue('apply_to');

        if (! $applyTo) {
            return $this;
        }

        $suggestionContext = ucfirst($applyTo) . 'FilterColumns';
        $this->addAssignFilter([
            'required' => true,
            'suggestionContext' => $suggestionContext,
            'description' => $this->translate(
                'This allows you to configure an assignment filter. Please feel'
                . ' free to combine as many nested operators as you want. The'
                . ' "contains" operator is valid for arrays only. Please use'
                . ' wildcards and the = (equals) operator when searching for'
                . ' partial string matches, like in *.example.com'
            )
        ]);

        return $this;
    }

    /**
     * @return $this
     */
    protected function addUsersElement()
    {
        $users = $this->enumUsers();
        if (empty($users)) {
            $this->addElement('select', 'users', [
                'label' => $this->translate('Users'),
                'description' => $this->translate('No User object has been created yet'),
                'multiOptions' => $this->optionalEnum([]),
            ]);
        } else {
            $this->addElement('extensibleSet', 'users', [
                'label'       => $this->translate('Users'),
                'description' => $this->translate(
                    'Users that should be notified by this notifications'
                ),
                'multiOptions' => $this->optionalEnum($users)
            ]);
        }

        $this->addElement('select', 'users_var', [
            'label' => $this->translate('Users Custom Variable'),
            'multiOptions' => $this->enumDirectorObjectFields('user'),
            'description' => $this->translate(
                'If defined, Users from this Custom Variable will be combined with single users chosen below. '
                . ' e.g.: when set to notification_contacts, this notification will pick Users from the Array'
                . ' service.vars.notification_contacts and fall back to host.vars.notification_contacts, in'
                . ' case the former one does not exist.'
                . ' Only Array type DirectorObject Fields for User objects are eligible for this feature.'
            )
        ]);

        return $this;
    }

    /**
     * @return $this
     */
    protected function addUsergroupsElement()
    {
        $groups = $this->enumUsergroups();
        if (empty($groups)) {
            $this->addElement('select', 'user_groups', [
                'label' => $this->translate('Users'),
                'description' => $this->translate('No UserGroup object has been created yet'),
                'multiOptions' => $this->optionalEnum([]),
            ]);
        } else {
            $this->addElement('extensibleSet', 'user_groups', [
                'label'       => $this->translate('User groups'),
                'description' => $this->translate(
                    'User groups that should be notified by this notifications'
                ),
                'multiOptions' => $this->optionalEnum($groups)
            ]);
        }

        $this->addElement('select', 'user_groups_var', [
            'label' => $this->translate('User Groups Custom Variable'),
            'multiOptions' => $this->enumDirectorObjectFields('usergroup'),
            'description' => $this->translate(
                'If defined, User Groups from this Custom Variable will be combined with single Groups chosen below. '
                . ' e.g.: when set to notification_groups, this notification will pick User Groups from the Array'
                . ' service.vars.notification_groups and fall back to host.vars.notification_groups, in'
                . ' case the former one does not exist'
                . ' Only Array type DirectorObject Fields for User objects are eligible for this feature.'
            )
        ]);

        return $this;
    }

    protected function enumDirectorObjectFields($objectType, $dataType = 'array')
    {
        $db = $this->db->getDbAdapter();
        $query = $db->select()
            ->from(['df' => 'director_datafield'], ['k' => 'df.varname', 'v' => 'df.varname'])
            ->join(
                ['dfs' => 'director_datafield_setting'],
                $db->quoteInto('df.id = dfs.datafield_id AND dfs.setting_name = ?', 'icinga_object_type'),
                []
            )
            ->join(
                ['dft' => 'director_datafield_setting'],
                $db->quoteInto('df.id = dft.datafield_id AND dft.setting_name = ?', 'data_type'),
                []
            )
            ->where('df.datatype = ?', DataTypeDirectorObject::class)
            ->where('dfs.setting_value = ?', $objectType)
            ->where('dft.setting_value = ?', $dataType)
            ->order('df.varname');

        return $this->optionalEnum($db->fetchPairs($query));
    }

    /**
     * @return self
     */
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

    /**
     * @return self
     */
    protected function addTimesElements()
    {
        $this->addElement(
            'text',
            'times_begin',
            array(
                'label' => $this->translate('First notification delay'),
                'description' => $this->translate(
                    'Delay until the first notification should be sent'
                ) . '. ' . $this->getTimeValueInfo()
            )
        );

        $this->addElement(
            'text',
            'times_end',
            array(
                'label' => $this->translate('Last notification'),
                'description' => $this->translate(
                    'When the last notification should be sent'
                ) . '. ' . $this->getTimeValueInfo()
            )
        );

        return $this;
    }

    protected function getTimeValueInfo()
    {
        return $this->translate(
            'Unit is seconds unless a suffix is given. Supported suffixes include'
            . ' ms (milliseconds), s (seconds), m (minutes), h (hours) and d (days).'
        );
    }

    /**
     * @return self
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
                    'The name of a time period which determines when this'
                    . ' notification should be triggered. Not set by default.'
                ),
                'multiOptions' => $this->optionalEnum($periods),
            )
        );

        return $this;
    }

    /**
     * @return self
     */
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
