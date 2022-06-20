<?php

namespace Icinga\Module\Director\Objects;

use Icinga\Data\Db\DbConnection;
use Icinga\Exception\NotFoundError;
use Icinga\Module\Director\Data\PropertiesFilter;
use Icinga\Module\Director\Db;
use Icinga\Module\Director\DirectorObject\Automation\ExportInterface;
use Icinga\Module\Director\Exception\DuplicateKeyException;
use Icinga\Module\Director\IcingaConfig\IcingaConfig;
use Icinga\Module\Director\IcingaConfig\IcingaConfigHelper as c;
use Icinga\Module\Director\IcingaConfig\IcingaLegacyConfigHelper as c1;
use Icinga\Module\Director\Objects\Extension\FlappingSupport;
use InvalidArgumentException;
use RuntimeException;

class IcingaHost extends IcingaObject implements ExportInterface
{
    use FlappingSupport;

    protected $table = 'icinga_host';

    protected $defaultProperties = array(
        'id'                      => null,
        'uuid'                    => null,
        'object_name'             => null,
        'object_type'             => null,
        'disabled'                => 'n',
        'display_name'            => null,
        'address'                 => null,
        'address6'                => null,
        'check_command_id'        => null,
        'max_check_attempts'      => null,
        'check_period_id'         => null,
        'check_interval'          => null,
        'retry_interval'          => null,
        'check_timeout'           => null,
        'enable_notifications'    => null,
        'enable_active_checks'    => null,
        'enable_passive_checks'   => null,
        'enable_event_handler'    => null,
        'enable_flapping'         => null,
        'enable_perfdata'         => null,
        'event_command_id'        => null,
        'flapping_threshold_high' => null,
        'flapping_threshold_low'  => null,
        'volatile'                => null,
        'zone_id'                 => null,
        'command_endpoint_id'     => null,
        'notes'                   => null,
        'notes_url'               => null,
        'action_url'              => null,
        'icon_image'              => null,
        'icon_image_alt'          => null,
        'has_agent'               => null,
        'master_should_connect'   => null,
        'accept_config'           => null,
        'custom_endpoint_name'    => null,
        'api_key'                 => null,
        'template_choice_id'      => null,
    );

    protected $relations = array(
        'check_command'    => 'IcingaCommand',
        'event_command'    => 'IcingaCommand',
        'check_period'     => 'IcingaTimePeriod',
        'command_endpoint' => 'IcingaEndpoint',
        'zone'             => 'IcingaZone',
        'template_choice'  => 'IcingaTemplateChoiceHost',
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
        'check_timeout'  => 'check_timeout',
        'retry_interval' => 'retry_interval',
    );

    protected $supportsCustomVars = true;

    protected $supportsGroups = true;

    protected $supportsImports = true;

    protected $supportsFields = true;

    protected $supportsChoices = true;

    protected $supportedInLegacy = true;

    /** @var HostGroupMembershipResolver */
    protected $hostgroupMembershipResolver;

    protected $uuidColumn = 'uuid';

    public static function enumProperties(
        DbConnection $connection = null,
        $prefix = '',
        $filter = null
    ) {
        $hostProperties = array();
        if ($filter === null) {
            $filter = new PropertiesFilter();
        }
        $realProperties = array_merge(['templates'], static::create()->listProperties());
        sort($realProperties);

        if ($filter->match(PropertiesFilter::$HOST_PROPERTY, 'name')) {
            $hostProperties[$prefix . 'name'] = 'name';
        }
        foreach ($realProperties as $prop) {
            if (!$filter->match(PropertiesFilter::$HOST_PROPERTY, $prop)) {
                continue;
            }

            if (substr($prop, -3) === '_id') {
                if ($prop === 'template_choice_id') {
                    continue;
                }
                $prop = substr($prop, 0, -3);
            }

            $hostProperties[$prefix . $prop] = $prop;
        }
        unset($hostProperties[$prefix . 'uuid']);
        unset($hostProperties[$prefix . 'custom_endpoint_name']);

        $hostVars = array();

        if ($connection instanceof Db) {
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

        $name = $this->getEndpointName();

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

        $endpoint = IcingaEndpoint::create($props, $this->connection);

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

    /**
    // @codingStandardsIgnoreStart
     * @param $value
     * @return string
     */
    protected function renderCustom_endpoint_name($value)
    {
        // @codingStandardsIgnoreEnd
        // When feature flag feature_custom_endpoint is enabled, render custom var
        if ($this->connection->settings()->get('feature_custom_endpoint') === 'y') {
            return c::renderKeyValue('vars._director_custom_endpoint_name', c::renderString($value));
        }

        return '';
    }

    /**
     * Returns the hostname or custom endpoint name of the Icinga agent
     *
     * @return string
     */
    public function getEndpointName()
    {
        $name = $this->getObjectName();

        if ($this->connection->settings()->get('feature_custom_endpoint') === 'y') {
            if (($customName = $this->get('custom_endpoint_name')) !== null) {
                $name = $customName;
            }
        }

        return $name;
    }

    public function getAgentListenPort()
    {
        $conn = $this->connection;
        $name = $this->getObjectName();
        if (IcingaEndpoint::exists($name, $conn)) {
            return IcingaEndpoint::load($name, $conn)->getResolvedPort();
        } else {
            return 5665;
        }
    }

    public function getUniqueIdentifier()
    {
        if ($this->isTemplate()) {
            return $this->getObjectName();
        } else {
            throw new RuntimeException(
                'getUniqueIdentifier() is supported by Host Templates only'
            );
        }
    }

    /**
     * @return object
     * @deprecated please use \Icinga\Module\Director\Data\Exporter
     * @throws \Icinga\Exception\NotFoundError
     */
    public function export()
    {
        // TODO: ksort in toPlainObject?
        $props = (array) $this->toPlainObject();
        $props['fields'] = $this->loadFieldReferences();
        ksort($props);

        return (object) $props;
    }

    /**
     * @param $plain
     * @param Db $db
     * @param bool $replace
     * @return IcingaHost
     * @throws DuplicateKeyException
     * @throws \Icinga\Exception\NotFoundError
     */
    public static function import($plain, Db $db, $replace = false)
    {
        $properties = (array) $plain;
        $name = $properties['object_name'];
        if ($properties['object_type'] !== 'template') {
            throw new InvalidArgumentException(sprintf(
                'Can import only Templates, got "%s" for "%s"',
                $properties['object_type'],
                $name
            ));
        }
        $key = $name;

        if ($replace && static::exists($key, $db)) {
            $object = static::load($key, $db);
        } elseif (static::exists($key, $db)) {
            throw new DuplicateKeyException(
                'Service Template "%s" already exists',
                $name
            );
        } else {
            $object = static::create([], $db);
        }

        // $object->newFields = $properties['fields'];
        unset($properties['fields']);
        $object->setProperties($properties);

        return $object;
    }

    /**
     * @deprecated please use \Icinga\Module\Director\Data\FieldReferenceLoader
     * @return array
     */
    protected function loadFieldReferences()
    {
        $db = $this->getDb();

        $res = $db->fetchAll(
            $db->select()->from([
                'hf' => 'icinga_host_field'
            ], [
                'hf.datafield_id',
                'hf.is_required',
                'hf.var_filter',
            ])->join(['df' => 'director_datafield'], 'df.id = hf.datafield_id', [])
                ->where('host_id = ?', $this->get('id'))
                ->order('varname ASC')
        );

        if (empty($res)) {
            return [];
        } else {
            foreach ($res as $field) {
                $field->datafield_id = (int) $field->datafield_id;
            }
            return $res;
        }
    }

    public function beforeDelete()
    {
        foreach ($this->fetchServices() as $service) {
            $service->delete();
        }
        foreach ($this->fetchServiceSets() as $set) {
            $set->delete();
        }

        parent::beforeDelete();
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
        $resolver->addObject($this);
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
    protected function renderTemplate_choice_id()
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

    protected function renderLegacyVolatile()
    {
        // not available for hosts in Icinga 1.x
        return;
    }

    protected function renderLegacyCustomExtensions()
    {
        $str = parent::renderLegacyCustomExtensions();

        if (($alias = $this->vars()->get('alias')) !== null) {
            $str .= c1::renderKeyValue('alias', $alias->getValue());
        }

        return $str;
    }

    /**
     * @return IcingaService[]
     */
    public function fetchServices()
    {
        $connection = $this->getConnection();
        $db = $connection->getDbAdapter();

        /** @var IcingaService[] $services */
        $services = IcingaService::loadAll(
            $connection,
            $db->select()->from('icinga_service')
                ->where('host_id = ?', $this->get('id'))
        );

        return $services;
    }

    /**
     * @return IcingaServiceSet[]
     */
    public function fetchServiceSets()
    {
        $connection = $this->getConnection();
        $db = $connection->getDbAdapter();

        /** @var IcingaServiceSet[] $sets */
        $sets = IcingaServiceSet::loadAll(
            $connection,
            $db->select()->from('icinga_service_set')
                ->where('host_id = ?', $this->get('id'))
        );

        return $sets;
    }

    /**
     * @return string
     */
    public function generateApiKey()
    {
        $key = sha1(
            (string) microtime(false)
            . $this->getObjectName()
            . rand(1, 1000000)
        );

        if ($this->dbHasApiKey($key)) {
            $key = $this->generateApiKey();
        }

        $this->set('api_key', $key);

        return $key;
    }

    protected function dbHasApiKey($key)
    {
        $db = $this->getDb();
        $query = $db->select()->from(
            ['o' => $this->getTableName()],
            'o.api_key'
        )->where('api_key = ?', $key);

        return $db->fetchOne($query) === $key;
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
