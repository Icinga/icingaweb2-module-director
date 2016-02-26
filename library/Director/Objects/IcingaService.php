<?php

namespace Icinga\Module\Director\Objects;

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
        return $this->renderRelationProperty('host', $this->host_id, 'host_name');
    }

    protected function renderCustomExtensions()
    {
        if ($this->command_endpoint_id !== null
            || $this->object_type !== 'object'
            || $this->getResolvedProperty('use_agent') !== 'y') {
            return '';
        }

        return $this->renderRelationProperty('host', $this->host_id, 'command_endpoint');
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

    /**
     * Use duration time renderer helper
     *
     * Avoid complaints for method names with underscore:
     * @codingStandardsIgnoreStart
     *
     * @return string
     */
    protected function renderCheck_Interval()
    {
        // @codingStandardsIgnoreEnd
        return $this->renderPropertyAsSeconds('check_interval');
    }

    /**
     * Use duration time renderer helper
     *
     * Avoid complaints for method names with underscore:
     * @codingStandardsIgnoreStart
     *
     * @return string
     */
    protected function renderRetry_Interval()
    {
        // @codingStandardsIgnoreEnd
        return $this->renderPropertyAsSeconds('retry_interval');
    }

    public function hasCheckCommand()
    {
        return $this->getResolvedProperty('check_command_id') !== null;
    }
}
