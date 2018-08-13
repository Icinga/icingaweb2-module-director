<?php

namespace Icinga\Module\Director\Objects;

use Icinga\Data\Filter\Filter;
use Icinga\Exception\IcingaException;
use Icinga\Module\Director\Data\PropertiesFilter;
use Icinga\Module\Director\Db;
use Icinga\Module\Director\Db\Cache\PrefetchCache;
use Icinga\Module\Director\IcingaConfig\IcingaConfig;
use Icinga\Module\Director\IcingaConfig\IcingaConfigHelper as c;
use Icinga\Module\Director\IcingaConfig\IcingaLegacyConfigHelper as c1;
use Icinga\Module\Director\Objects\Extension\FlappingSupport;
use Icinga\Module\Director\Resolver\HostServiceBlacklist;
use InvalidArgumentException;
use RuntimeException;

class IcingaService extends IcingaObject
{
    use FlappingSupport;

    protected $table = 'icinga_service';

    protected $defaultProperties = [
        'id'                      => null,
        'object_name'             => null,
        'object_type'             => null,
        'disabled'                => 'n',
        'display_name'            => null,
        'host_id'                 => null,
        'service_set_id'          => null,
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
        'use_agent'               => null,
        'apply_for'               => null,
        'use_var_overrides'       => null,
        'assign_filter'           => null,
        'template_choice_id'      => null,
    ];

    protected $relations = [
        'host'             => 'IcingaHost',
        'service_set'      => 'IcingaServiceSet',
        'check_command'    => 'IcingaCommand',
        'event_command'    => 'IcingaCommand',
        'check_period'     => 'IcingaTimePeriod',
        'command_endpoint' => 'IcingaEndpoint',
        'zone'             => 'IcingaZone',
        'template_choice'  => 'IcingaTemplateChoiceService',
    ];

    protected $booleans = [
        'enable_notifications'  => 'enable_notifications',
        'enable_active_checks'  => 'enable_active_checks',
        'enable_passive_checks' => 'enable_passive_checks',
        'enable_event_handler'  => 'enable_event_handler',
        'enable_flapping'       => 'enable_flapping',
        'enable_perfdata'       => 'enable_perfdata',
        'volatile'              => 'volatile',
        'use_agent'             => 'use_agent',
        'use_var_overrides'     => 'use_var_overrides',
    ];

    protected $intervalProperties = [
        'check_interval' => 'check_interval',
        'check_timeout'  => 'check_timeout',
        'retry_interval' => 'retry_interval',
    ];

    protected $supportsGroups = true;

    protected $supportsCustomVars = true;

    protected $supportsFields = true;

    protected $supportsImports = true;

    protected $supportsApplyRules = true;

    protected $supportsSets = true;

    protected $supportsChoices = true;

    protected $supportedInLegacy = true;

    protected $keyName = ['host_id', 'service_set_id', 'object_name'];

    protected $prioritizedProperties = ['host_id'];

    protected $propertiesNotForRendering = [
        'id',
        'object_name',
        'object_type',
        'apply_for'
    ];

    /**
     * @return IcingaCommand
     * @throws IcingaException
     * @throws \Icinga\Exception\NotFoundError
     */
    public function getCheckCommand()
    {
        $id = $this->getSingleResolvedProperty('check_command_id');
        return IcingaCommand::loadWithAutoIncId(
            $id,
            $this->getConnection()
        );
    }

    /**
     * @return bool
     */
    public function isApplyRule()
    {
        if ($this->hasBeenAssignedToHostTemplate()) {
            return true;
        }

        return $this->hasProperty('object_type')
            && $this->get('object_type') === 'apply';
    }

    /**
     * @return bool
     */
    public function usesVarOverrides()
    {
        return $this->get('use_var_overrides') === 'y';
    }

    /**
     * @param string $key
     * @return $this
     */
    protected function setKey($key)
    {
        if (is_int($key)) {
            $this->set('id', $key);
        } elseif (is_array($key)) {
            foreach (['id', 'host_id', 'service_set_id', 'object_name'] as $k) {
                if (array_key_exists($k, $key)) {
                    $this->set($k, $key[$k]);
                }
            }
        } else {
            parent::setKey($key);
        }

        return $this;
    }

    /**
     * @param $name
     * @return $this
     * @codingStandardsIgnoreStart
     */
    protected function setObject_Name($name)
    {
        // @codingStandardsIgnoreEnd

        if ($name === null && $this->isApplyRule()) {
            $name = '';
        }

        return $this->reallySet('object_name', $name);
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

        return $this->renderRelationProperty('host', $this->get('host_id'), 'host_name');
    }

    /**
     * @param IcingaConfig $config
     * @throws IcingaException
     */
    public function renderToLegacyConfig(IcingaConfig $config)
    {
        if ($this->get('service_set_id') !== null) {
            return;
        } elseif ($this->isApplyRule()) {
            $this->renderLegacyApplyToConfig($config);
        } else {
            parent::renderToLegacyConfig($config);
        }
    }

    /**
     * @param IcingaConfig $config
     * @throws IcingaException
     */
    protected function renderLegacyApplyToConfig(IcingaConfig $config)
    {
        $conn = $this->getConnection();

        $assign_filter = $this->get('assign_filter');
        $filter = Filter::fromQueryString($assign_filter);
        $hosts = HostApplyMatches::forFilter($filter, $conn);
        $this->set('object_type', 'object');
        $this->set('assign_filter', null);

        foreach ($hosts as $hostname) {
            $file = $this->legacyHostnameServicesFile($hostname, $config);
            $this->set('host', $hostname);
            $file->addLegacyObject($this);
        }

        $this->set('host', null);
        $this->set('object_type', 'apply');
        $this->set('assign_filter', $assign_filter);
    }

    /**
     * @param string $hostname
     * @param IcingaConfig $config
     * @return \Icinga\Module\Director\IcingaConfig\IcingaConfigFile
     * @throws \Icinga\Exception\NotFoundError
     */
    protected function legacyHostnameServicesFile($hostname, IcingaConfig $config)
    {
        return $config->configFile(
            sprintf(
                'director/%s/service_apply',
                IcingaHost::load($hostname, $this->getConnection())
                    ->getRenderingZone($config)
            ),
            '.cfg'
        );
    }

    /**
     * @return string
     */
    public function toLegacyConfigString()
    {
        if ($this->get('service_set_id') !== null) {
            return '';
        }

        if ($this->isApplyRule()) {
            throw new InvalidArgumentException('Apply Services can not be rendered directly.');
        }

        $str = parent::toLegacyConfigString();

        if (! $this->isDisabled()
            && $this->get('host_id')
            && $this->getRelated('host')->isDisabled()
        ) {
            return "# --- This services host has been disabled ---\n"
                . preg_replace('~^~m', '# ', trim($str))
                . "\n\n";
        } else {
            return $str;
        }
    }

    /**
     * @return string
     */
    public function toConfigString()
    {
        if ($this->get('service_set_id')) {
            return '';
        }
        $str = parent::toConfigString();

        if (! $this->isDisabled()
            && $this->get('host_id')
            && $this->getRelated('host')->isDisabled()
        ) {
            return "/* --- This services host has been disabled ---\n$str*/\n";
        } else {
            return $str;
        }
    }

    /**
     * @return string
     */
    protected function renderObjectHeader()
    {
        if ($this->isApplyRule()
            && !$this->hasBeenAssignedToHostTemplate()
            && $this->get('apply_for') !== null
        ) {
            $name = $this->getObjectName();
            $extraName = '';

            if (c::stringHasMacro($name)) {
                $extraName = c::renderKeyValue('name', c::renderStringWithVariables($name));
                $name = '';
            } elseif ($name !== '') {
                $name = ' ' . c::renderString($name);
            }

            return sprintf(
                "%s %s%s for (config in %s) {\n",
                $this->getObjectTypeName(),
                $this->getType(),
                $name,
                $this->get('apply_for')
            ) . $extraName;
        }

        return parent::renderObjectHeader();
    }

    /**
     * @return string
     */
    protected function getLegacyObjectKeyName()
    {
        if ($this->isTemplate()) {
            return 'name';
        } else {
            return 'service_description';
        }
    }

    /**
     * @return bool
     */
    protected function hasBeenAssignedToHostTemplate()
    {
        $hostId = $this->get('host_id');

        return $hostId && $this->getRelatedObject(
            'host',
            $hostId
        )->isTemplate();
    }

    /**
     * @return string
     */
    protected function renderSuffix()
    {
        if ($this->isApplyRule() || $this->usesVarOverrides()) {
            return $this->renderImportHostVarOverrides() . parent::renderSuffix();
        } else {
            return parent::renderSuffix();
        }
    }

    /**
     * @return string
     */
    protected function renderImportHostVarOverrides()
    {
        if (! $this->connection) {
            throw new RuntimeException(
                'Cannot render services without an assigned DB connection'
            );
        }

        return "\n    import DirectorOverrideTemplate\n";
    }

    /**
     * @return string
     */
    protected function renderCustomExtensions()
    {
        $output = '';

        if ($this->hasBeenAssignedToHostTemplate()) {
            // TODO: use assignment renderer?
            $filter = sprintf(
                'assign where %s in host.templates',
                c::renderString($this->get('host'))
            );

            $output .= "\n    " . $filter . "\n";
        }

        $blacklist = $this->getBlacklistedHostnames();
        if (! empty($blacklist)) {
            if (count($blacklist) === 1) {
                $output .= sprintf(
                    "    ignore where host.name == %s\n",
                    c::renderString($blacklist[0])
                );
            } else {
                $output .= sprintf(
                    "    ignore where host.name in %s\n",
                    c::renderArray($blacklist)
                );
            }
        }

        // A hand-crafted command endpoint overrides use_agent
        if ($this->get('command_endpoint_id') !== null) {
            return $output;
        }

        if ($this->get('use_agent') === 'y') {
            return $output . c::renderKeyValue('command_endpoint', 'host_name');
        } elseif ($this->get('use_agent') === 'n') {
            return $output . c::renderKeyValue('command_endpoint', c::renderPhpValue(null));
        } else {
            return $output;
        }
    }

    /**
     * @return array
     */
    public function getBlacklistedHostnames()
    {
        // Hint: if ($this->isApplyRule()) would be nice, but apply rules are
        // not enough, one might want to blacklist single services from Sets
        // assigned to single Hosts.
        if (PrefetchCache::shouldBeUsed()) {
            $lookup = PrefetchCache::instance()->hostServiceBlacklist();
        } else {
            $lookup = new HostServiceBlacklist($this->getConnection());
        }

        return $lookup->getBlacklistedHostnamesForService($this);
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
        return '';
    }

    public function renderUse_var_overrides()
    {
        return '';
    }

    protected function renderTemplate_choice_id()
    {
        return '';
    }

    protected function renderLegacyDisplay_Name()
    {
        // @codingStandardsIgnoreEnd
        return c1::renderKeyValue('display_name', $this->get('display_name'));
    }

    public function hasCheckCommand()
    {
        return $this->getSingleResolvedProperty('check_command_id') !== null;
    }

    public function getOnDeleteUrl()
    {
        if ($this->get('host_id')) {
            return 'director/host/services?name=' . rawurlencode($this->get('host'));
        } else {
            return parent::getOnDeleteUrl();
        }
    }

    protected function getDefaultZone(IcingaConfig $config = null)
    {
        if ($this->get('host_id') === null) {
            return parent::getDefaultZone();
        } else {
            return $this->getRelatedObject('host', $this->get('host_id'))
                ->getRenderingZone($config);
        }
    }

    /**
     * @return string
     */
    public function createWhere()
    {
        $where = parent::createWhere();
        if (! $this->hasBeenLoadedFromDb()) {
            if (null === $this->get('service_set_id')
                && null === $this->get('host_id')
                && null === $this->get('id')
            ) {
                $where .= " AND object_type = 'template'";
            }
        }

        return $where;
    }


    /**
     * TODO: Duplicate code, clean this up, split it into multiple methods
     * @param Db|null $connection
     * @param string $prefix
     * @param null $filter
     * @return array
     */
    public static function enumProperties(
        Db $connection = null,
        $prefix = '',
        $filter = null
    ) {
        $serviceProperties = [];
        if ($filter === null) {
            $filter = new PropertiesFilter();
        }
        $realProperties = static::create()->listProperties();
        sort($realProperties);

        if ($filter->match(PropertiesFilter::$SERVICE_PROPERTY, 'name')) {
            $serviceProperties[$prefix . 'name'] = 'name';
        }
        foreach ($realProperties as $prop) {
            if (!$filter->match(PropertiesFilter::$SERVICE_PROPERTY, $prop)) {
                continue;
            }

            if (substr($prop, -3) === '_id') {
                if ($prop === 'template_choice_id') {
                    continue;
                }
                $prop = substr($prop, 0, -3);
            }

            $serviceProperties[$prefix . $prop] = $prop;
        }

        $serviceVars = [];

        if ($connection !== null) {
            foreach ($connection->fetchDistinctServiceVars() as $var) {
                if ($filter->match(PropertiesFilter::$CUSTOM_PROPERTY, $var->varname, $var)) {
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
        }

        //$properties['vars.*'] = 'Other custom variable';
        ksort($serviceVars);

        $props = mt('director', 'Service properties');
        $vars  = mt('director', 'Custom variables');

        $properties = [];
        if (!empty($serviceProperties)) {
            $properties[$props] = $serviceProperties;
            $properties[$props][$prefix . 'groups'] = 'Groups';
        }

        if (!empty($serviceVars)) {
            $properties[$vars] = $serviceVars;
        }

        $hostProps = mt('director', 'Host properties');
        $hostVars  = mt('director', 'Host Custom variables');

        $hostProperties = IcingaHost::enumProperties($connection, 'host.');

        if (array_key_exists($hostProps, $hostProperties)) {
            $p = $hostProperties[$hostProps];
            if (!empty($p)) {
                $properties[$hostProps] = $p;
            }
        }

        if (array_key_exists($vars, $hostProperties)) {
            $p = $hostProperties[$vars];
            if (!empty($p)) {
                $properties[$hostVars] = $p;
            }
        }

        return $properties;
    }

    protected function beforeStore()
    {
        parent::beforeStore();
        if ($this->isObject()
            && $this->get('service_set_id') === null
            && $this->get('host_id') === null
        ) {
            throw new InvalidArgumentException(
                'Cannot store a Service object without a related host'
            );
        }
    }
}
