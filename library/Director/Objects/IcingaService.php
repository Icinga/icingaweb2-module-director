<?php

namespace Icinga\Module\Director\Objects;

use Icinga\Data\Db\DbConnection;
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

    protected $prioritizedProperties = array('host_id');

    public static function enumProperties(DbConnection $connection = null, $prefix = '')
    {
        $serviceProperties = array($prefix . 'name' => 'name');
	$realProperties = static::create()->listProperties();
	sort($realProperties);

	$blacklist = array(
            'id',
            'object_name',
            'object_type',
        );

        foreach ($realProperties as $prop) {
            if (in_array($prop, $blacklist)) {
                continue;
            }

            if (substr($prop, -3) === '_id') {
                $prop = substr($prop, 0, -3);
            }

            $serviceProperties[$prefix . $prop] = $prop;
        }

        $serviceVars = array();
        if ($connection !== null) {
            foreach ($connection->fetchDistinctServiceVars() as $var) {
                if ($var->datatype) {
                    $serviceVars[$prefix . 'vars.' . $var->varname] = sprintf(
                        '%s (%s)',
                        $var->varname,
                        $var->caption
                    );
                } else {
                    $serviceVars[$prefix . 'vars.' . $var->varname] = $var->varname;
                }
            }
        }

        ksort($serviceVars);


        $props = mt('director', 'Service properties');
        $vars  = mt('director', 'Service Custom variables');
        $properties = array(
            $props => $serviceProperties,
        );

        if (!empty($serviceVars)) {
            $properties[$vars] = $serviceVars;
        }

        return $properties;
    }

    public function getCheckCommand()
    {
        $id = $this->getResolvedProperty('check_command_id');
        return IcingaCommand::loadWithAutoIncId(
            $id,
            $this->getConnection()
        );
    }

    public function isApplyRule()
    {
        if ($this->hasBeenAssignedToHostTemplate()) {
            return true;
        }

        return $this->hasProperty('object_type')
            && $this->object_type === 'apply';
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
        // A hand-crafted command endpoint overrides use_agent
        if ($this->command_endpoint_id !== null) {
            return '';
        }

        // In case use_agent isn't defined, do nothing
        // TODO: what if we inherit use_agent and override it with 'n'?
        if ($this->use_agent !== 'y') {
            return '';
        }

        return c::renderKeyValue('command_endpoint', 'host_name');
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
