<?php

namespace Icinga\Module\Director\Objects;

use Icinga\Module\Director\Web\Form\DirectorObjectForm;

class IcingaHost extends IcingaObject
{
    protected $table = 'icinga_host';

    protected $defaultProperties = array(
        'id'                    => null,
        'object_name'           => null,
        'display_name'          => null,
        'address'               => null,
        'address6'              => null,
        'check_command_id'      => null,
        'max_check_attempts'    => null,
        'check_period_id'       => null,
        'check_interval'        => null,
        'retry_interval'        => null,
        'enable_notifications'  => null,
        'enable_active_checks'  => null,
        'enable_passive_checks' => null,
        'enable_event_handler'  => null,
        'enable_flapping'       => null,
        'enable_perfdata'       => null,
        'event_command_id'      => null,
        'flapping_threshold'    => null,
        'volatile'              => null,
        'zone_id'               => null,
        'command_endpoint_id'   => null,
        'notes'                 => null,
        'notes_url'             => null,
        'action_url'            => null,
        'icon_image'            => null,
        'icon_image_alt'        => null,
        'object_type'           => null,
    );

    protected $supportsCustomVars = true;

    protected $supportsGroups = true;

    protected $supportsImports = true;

    protected $supportsFields = true;

    protected function renderCheck_command_id()
    {
        return $this->renderCommandProperty($this->check_command_id);
    }

    protected function renderEnable_notifications()
    {
        return $this->renderBooleanProperty('enable_notifications');
    }

    protected function renderEnable_active_checks()
    {
        return $this->renderBooleanProperty('enable_active_checks');
    }

    protected function renderEnable_passive_checks()
    {
        return $this->renderBooleanProperty('enable_passive_checks');
    }

    protected function renderEnable_event_handler()
    {
        return $this->renderBooleanProperty('enable_event_handler');
    }

    protected function renderEnable_flapping()
    {
        return $this->renderBooleanProperty('enable_passive_checks');
    }

    protected function renderEnable_perfdata()
    {
        return $this->renderBooleanProperty('enable_perfdata');
    }

    protected function renderVolatile()
    {
        return $this->renderBooleanProperty('volatile');
    }
}
