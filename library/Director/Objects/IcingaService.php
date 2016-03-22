<?php

namespace Icinga\Module\Director\Objects;

use Icinga\Module\Director\IcingaConfig\IcingaConfigHelper as c;

class IcingaService extends IcingaObject
{
    protected $table = 'icinga_service';

    protected $defaultProperties = array(
        'id'                    => null,
        'object_name'           => null,
        'object_type'           => null,
        'disabled'              => 'n',
        'display_name'          => null,
        'host_id'               => null,
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
        'use_agent'             => null,
    );

    protected $relations = array(
        'host'             => 'IcingaHost',
        'check_command'    => 'IcingaCommand',
        'event_command'    => 'IcingaCommand',
        'check_period'     => 'IcingaTimePeriod',
        'command_endpoint' => 'IcingaEndpoint',
        'zone'             => 'IcingaZone',
    );

    protected $booleans = array(
        'enable_notifications'  => 'enable_notifications',
        'enable_active_checks'  => 'enable_active_checks',
        'enable_passive_checks' => 'enable_passive_checks',
        'enable_event_handler'  => 'enable_event_handler',
        'enable_flapping'       => 'enable_flapping',
        'enable_perfdata'       => 'enable_perfdata',
        'volatile'              => 'volatile',
        'use_agent'             => 'use_agent',
    );

    protected $intervalProperties = array(
        'check_interval' => 'check_interval',
        'retry_interval' => 'retry_interval',
    );

    protected $supportsGroups = true;

    protected $supportsCustomVars = true;

    protected $supportsFields = true;

    protected $supportsImports = true;

    protected $supportsApplyRules = true;

    protected $keyName = array('host_id', 'object_name');

    public function getCheckCommand()
    {
        $id = $this->getResolvedProperty('check_command_id');
        return IcingaCommand::loadWithAutoIncId(
            $id,
            $this->getConnection()
        );
    }

    protected function setKey($key)
    {
        if (is_int($key)) {
            $this->id = $key;
        } elseif (is_array($key)) {
            foreach (array('id', 'host_id', 'object_name') as $k) {
                if (array_key_exists($k, $key)) {
                    $this->set($k, $key[$k]);
                }
            }
        } else {
            return parent::setKey($key);
        }

        return $this;
    }

    /**
     * Render host_id as host_name
     *
     * Avoid complaints for method names with underscore:
     * @codingStandardsIgnoreStart
     *
     * @return string
     */
    public function renderHost_id()
    {
        // @codingStandardsIgnoreEnd

        if ($this->hasBeenAssignedToHostTemplate()) {
            return '';
        }

        return $this->renderRelationProperty('host', $this->host_id, 'host_name');
    }

    protected function renderObjectHeader()
    {
        if ($this->hasBeenAssignedToHostTemplate()) {

            return sprintf(
                "%s %s %s {\n",
                'apply',
                $this->getType(),
                c::renderString($this->getObjectName())
            );

        } else {
            return parent::renderObjectHeader();
        }
    }

    protected function renderAssignments()
    {
        if (! $this->hasBeenAssignedToHostTemplate()) {
            return parent::renderAssignments();
        }

        // TODO: use assignment renderer, escape host
        $filter = sprintf(
            'assign where "%s" in host.templates',
            $this->host
        );

        return "\n    " . $filter . "\n";
    }

    protected function hasBeenAssignedToHostTemplate()
    {
        return $this->host_id && $this->getRelatedObject(
            'host',
            $this->host_id
        )->object_type === 'template';
    }

    protected function renderCustomExtensions()
    {
        if ($this->command_endpoint_id !== null
            || $this->object_type !== 'object'
            || $this->getResolvedProperty('use_agent') !== 'y') {
            return '';
        }

        if ($this->hasBeenAssignedToHostTemplate()) {
            return c::renderKeyValue('command_endpoint', 'host.name');
        } else {
            return $this->renderRelationProperty('host', $this->host_id, 'command_endpoint');
        }
    }

    /**
     * Do not render internal property
     *
     * Avoid complaints for method names with underscore:
     * @codingStandardsIgnoreStart
     *
     * @return string
     */
    public function renderUse_agent()
    {
        // @codingStandardsIgnoreEnd
        return '';
    }

    public function hasCheckCommand()
    {
        return $this->getResolvedProperty('check_command_id') !== null;
    }

    public function getOnDeleteUrl()
    {
        if ($this->host_id) {
            return 'director/host/services?name=' . rawurlencode($this->host);
        } else {
            return parent::getOnDeleteUrl();
        }
    }
}
