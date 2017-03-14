<?php

namespace Icinga\Module\Director\Objects;

use Icinga\Data\Db\DbConnection;
use Icinga\Exception\NotFoundError;
use Icinga\Module\Director\Data\PropertiesFilter;
use Icinga\Module\Director\Db;
use Icinga\Module\Director\IcingaConfig\IcingaConfig;
use Icinga\Module\Director\IcingaConfig\IcingaLegacyConfigHelper as c1;
use Icinga\Module\Director\Web\Form\DirectorObjectForm;

class IcingaHost extends IcingaObject
{
    protected $table = 'icinga_host';

    protected $defaultProperties = array(
        'id'                    => null,
        'object_name'           => null,
        'object_type'           => null,
        'disabled'              => 'n',
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
        'has_agent'             => null,
        'master_should_connect' => null,
        'accept_config'         => null,
        'api_key'               => null,
    );

    protected $relations = array(
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
        'has_agent'             => 'has_agent',
        'master_should_connect' => 'master_should_connect',
        'accept_config'         => 'accept_config',
    );

    protected $intervalProperties = array(
        'check_interval' => 'check_interval',
        'retry_interval' => 'retry_interval',
    );

    protected $supportsCustomVars = true;

    protected $supportsGroups = true;

    protected $supportsImports = true;

    protected $supportsFields = true;

    protected $supportedInLegacy = true;

    /** @var HostGroupMembershipResolver */
    protected $hostgroupMembershipResolver;

    public static function enumProperties(
        DbConnection $connection = null,
        $prefix = '',
        $filter = null
    ) {
        $hostProperties = array();
        if ($filter === null) {
            $filter = new PropertiesFilter();
        }
        $realProperties = static::create()->listProperties();
        sort($realProperties);

        if ($filter->match(PropertiesFilter::$HOST_PROPERTY, 'name')) {
            $hostProperties[$prefix . 'name'] = 'name';
        }
        foreach ($realProperties as $prop) {
            if (!$filter->match(PropertiesFilter::$HOST_PROPERTY, $prop)) {
                continue;
            }

            if (substr($prop, -3) === '_id') {
                $prop = substr($prop, 0, -3);
            }

            $hostProperties[$prefix . $prop] = $prop;
        }

        $hostVars = array();

        if ($connection !== null) {
            foreach ($connection->fetchDistinctHostVars() as $var) {
                if ($filter->match(PropertiesFilter::$CUSTOM_PROPERTY, $var->varname, $var)) {
                    if ($var->datatype) {
                        $hostVars[$prefix . 'vars.' . $var->varname] = sprintf(
                            '%s (%s)',
                            $var->varname,
                            $var->caption
                        );
                    } else {
                        $hostVars[$prefix . 'vars.' . $var->varname] = $var->varname;
                    }
                }
            }
        }

        //$properties['vars.*'] = 'Other custom variable';
        ksort($hostVars);


        $props = mt('director', 'Host properties');
        $vars  = mt('director', 'Custom variables');

        $properties = array();
        if (!empty($hostProperties)) {
            $properties[$props] = $hostProperties;
            $properties[$props][$prefix . 'groups'] = 'Groups';
        }

        if (!empty($hostVars)) {
            $properties[$vars] = $hostVars;
        }

        return $properties;
    }

    public function getCheckCommand()
    {
        $id = $this->getSingleResolvedProperty('check_command_id');
        return IcingaCommand::loadWithAutoIncId(
            $id,
            $this->getConnection()
        );
    }

    public function hasCheckCommand()
    {
        return $this->getSingleResolvedProperty('check_command_id') !== null;
    }

    public function renderToConfig(IcingaConfig $config)
    {
        parent::renderToConfig($config);

        // TODO: We might alternatively let the whole config fail in case we have
        //       used use_agent together with a legacy config
        if (! $config->isLegacy()) {
            $this->renderAgentZoneAndEndpoint($config);
        }
    }

    public function renderAgentZoneAndEndpoint(IcingaConfig $config = null)
    {
        if (!$this->isObject()) {
            return;
        }

        if ($this->isDisabled()) {
            return;
        }

        if ($this->getRenderingZone($config) === self::RESOLVE_ERROR) {
            return;
        }

        if ($this->getSingleResolvedProperty('has_agent') !== 'y') {
            return;
        }

        $name = $this->object_name;
        if (IcingaEndpoint::exists($name, $this->connection)) {
            return;
        }

        $props = array(
            'object_name'  => $name,
            'object_type'  => 'object',
            'log_duration' => 0
        );

        if ($this->getSingleResolvedProperty('master_should_connect') === 'y') {
            $props['host'] = $this->getSingleResolvedProperty('address');
        }

        $props['zone_id'] = $this->getSingleResolvedProperty('zone_id');

        $endpoint = IcingaEndpoint::create($props);

        $zone = IcingaZone::create(array(
            'object_name' => $name,
        ), $this->connection)->setEndpointList(array($name));

        if ($props['zone_id']) {
            $zone->parent_id = $props['zone_id'];
        } else {
            $zone->parent = $this->connection->getMasterZoneName();
        }

        $pre = 'zones.d/' . $this->getRenderingZone($config) . '/';
        $config->configFile($pre . 'agent_endpoints')->addObject($endpoint);
        $config->configFile($pre . 'agent_zones')->addObject($zone);
    }

    public function hasAnyOverridenServiceVars()
    {
        $varname = $this->getServiceOverrivesVarname();
        return isset($this->vars()->$varname);
    }

    public function getAllOverriddenServiceVars()
    {
        if ($this->hasAnyOverridenServiceVars()) {
            $varname = $this->getServiceOverrivesVarname();
            return $this->vars()->$varname->getValue();
        } else {
            return (object) array();
        }
    }

    public function hasOverriddenServiceVars($service)
    {
        $all = $this->getAllOverriddenServiceVars();
        return property_exists($all, $service);
    }

    public function getOverriddenServiceVars($service)
    {
        if ($this->hasOverriddenServiceVars($service)) {
            $all = $this->getAllOverriddenServiceVars();
            return $all->$service;
        } else {
            return (object) array();
        }
    }

    public function overrideServiceVars($service, $vars)
    {
        // For PHP < 5.5.0:
        $array = (array) $vars;
        if (empty($array)) {
            return $this->unsetOverriddenServiceVars($service);
        }

        $all = $this->getAllOverriddenServiceVars();
        $all->$service = $vars;
        $varname = $this->getServiceOverrivesVarname();
        $this->vars()->$varname = $all;

        return $this;
    }

    public function unsetOverriddenServiceVars($service)
    {
        if ($this->hasOverriddenServiceVars($service)) {
            $all = (array) $this->getAllOverriddenServiceVars();
            unset($all[$service]);

            $varname = $this->getServiceOverrivesVarname();
            if (empty($all)) {
                unset($this->vars()->$varname);
            } else {
                $this->vars()->$varname = (object) $all;
            }
        }

        return $this;
    }

    protected function notifyResolvers()
    {
        $resolver = $this->getHostGroupMembershipResolver();
        $resolver->addHost($this);
        $resolver->refreshDb();

        return $this;
    }

    protected function getHostGroupMembershipResolver()
    {
        if ($this->hostgroupMembershipResolver === null) {
            $this->hostgroupMembershipResolver = new HostGroupMembershipResolver(
                $this->getConnection()
            );
        }

        return $this->hostgroupMembershipResolver;
    }

    public function setHostGroupMembershipResolver(HostGroupMembershipResolver $resolver)
    {
        $this->hostgroupMembershipResolver = $resolver;
        return $this;
    }

    protected function getServiceOverrivesVarname()
    {
        return $this->connection->settings()->override_services_varname;
    }

    /**
     * Internal property, will not be rendered
     *
     * Avoid complaints for method names with underscore:
     * @codingStandardsIgnoreStart
     *
     * @return string
     */
    protected function renderHas_Agent()
    {
        return '';
    }

    /**
     * Internal property, will not be rendered
     *
     * @return string
     */
    protected function renderMaster_should_connect()
    {
        return '';
    }

    /**
     * Internal property, will not be rendered
     *
     * @return string
     */
    protected function renderApi_key()
    {
        return '';
    }

    /**
     * Internal property, will not be rendered
     *
     * @return string
     */
    protected function renderAccept_config()
    {
        // @codingStandardsIgnoreEnd
        return '';
    }

    /**
     * @codingStandardsIgnoreStart
     *
     * @return string
     */
    protected function renderLegacyDisplay_Name()
    {
        // @codingStandardsIgnoreEnd
        return c1::renderKeyValue('display_name', $this->display_name);
    }

    protected function renderLegacyCustomExtensions()
    {
        $str = parent::renderLegacyCustomExtensions();

        if (($alias = $this->vars()->get('alias')) !== null) {
            $str .= c1::renderKeyValue('alias', $alias->getValue());
        }

        return $str;
    }

    public static function loadWithApiKey($key, Db $db)
    {
        $query = $db->getDbAdapter()
            ->select()
            ->from('icinga_host')
            ->where('api_key = ?', $key);

        $result = self::loadAll($db, $query);
        if (count($result) !== 1) {
            throw new NotFoundError('Got invalid API key "%s"', $key);
        }

        return current($result);
    }
}
