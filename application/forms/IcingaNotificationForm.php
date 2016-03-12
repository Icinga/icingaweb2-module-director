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
             ->addIntervalElement()
             ->addPeriodElement()
             ->addTimesElements()
             ->addDisabledElement()
             ->addCommandElements()
             ->addEventFilterElements()
             ->groupMainProperties()
             ->setButtons();
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
                'label' => $this->translate('Times end'),
                'description' => $this->translate(
                    'When the last notification should be sent'
                )
            )
        );

        return $this;
    }

    protected function addPeriodElement()
    {
        $this->addElement(
            'select',
            'period',
            array(
                'label' => $this->translate('Time period'),
                'description' => $this->translate(
                    'The name of a time period which determines when this'
                    . ' notification should be triggered. Not set by default.'
                )
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
}
