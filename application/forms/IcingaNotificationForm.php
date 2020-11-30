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

    /**
     * @return $this
     */
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
                    'Delay unless the first notification should be sent'
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
