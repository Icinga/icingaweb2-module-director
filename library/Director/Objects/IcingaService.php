<?php

namespace Icinga\Module\Director\Objects;

use Icinga\Data\Filter\Filter;
use Icinga\Exception\IcingaException;
use Icinga\Module\Director\CustomVariable\CustomVariables;
use Icinga\Module\Director\Data\PropertiesFilter;
use Icinga\Module\Director\DataType\DataTypeArray;
use Icinga\Module\Director\Db;
use Icinga\Module\Director\Db\Cache\PrefetchCache;
use Icinga\Module\Director\DirectorObject\Automation\ExportInterface;
use Icinga\Module\Director\IcingaConfig\IcingaConfig;
use Icinga\Module\Director\IcingaConfig\IcingaConfigHelper as c;
use Icinga\Module\Director\IcingaConfig\IcingaLegacyConfigHelper as c1;
use Icinga\Module\Director\Objects\Extension\FlappingSupport;
use Icinga\Module\Director\Resolver\HostServiceBlacklist;
use InvalidArgumentException;
use RuntimeException;

class IcingaService extends IcingaObject implements ExportInterface
{
    use FlappingSupport;

    protected $table = 'icinga_service';

    protected $uuidColumn = 'uuid';

    protected $defaultProperties = [
        'id'                      => null,
        'uuid'                    => null,
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

    protected $supportsCustomVariables = true;

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

    /** @var ServiceGroupMembershipResolver */
    protected $servicegroupMembershipResolver;

    /** @var array|null Whitelist to be used in custom variables of apply for rule */
    protected ?array $applyForWhiteList = null;

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

    public function getUniqueIdentifier()
    {
        if ($this->isTemplate()) {
            return $this->getObjectName();
        } else {
            throw new RuntimeException(
                'getUniqueIdentifier() is supported by Service Templates only'
            );
        }
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
        if ($this->hasBeenAssignedToHostTemplate()) {
            return '';
        }

        return $this->renderRelationProperty('host', $this->get('host_id'), 'host_name');
    }

    /**
     * @codingStandardsIgnoreStart
     */
    protected function renderLegacyHost_id($value)
    {
        // @codingStandardsIgnoreEnd
        if (is_array($value)) {
            $blacklisted = $this->getBlacklistedHostnames();
            $c = c1::renderKeyValue('host_name', c1::renderArray(array_diff($value, $blacklisted)));

            // blacklisted in this (zoned) scope?
            $bl = array_intersect($blacklisted, $value);
            if (! empty($bl)) {
                $c .= c1::renderKeyValue('# ignored on', c1::renderArray($bl));
            }

            return $c;
        } else {
            return parent::renderLegacyHost_id($value);
        }
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
        $hostnames = HostApplyMatches::forFilter($filter, $conn);

        $this->set('object_type', 'object');

        foreach ($this->mapHostsToZones($hostnames) as $zone => $names) {
            $blacklisted = $this->getBlacklistedHostnames();
            $zoneNames = array_diff($names, $blacklisted);

            $disabled = [];
            foreach ($zoneNames as $name) {
                if (IcingaHost::load($name, $this->getConnection())->isDisabled()) {
                    $disabled[] = $name;
                }
            }
            $zoneNames = array_diff($zoneNames, $disabled);

            if (empty($zoneNames)) {
                continue;
            }

            $this->set('host_id', $zoneNames);

            $config->configFile('director/' . $zone . '/service_apply', '.cfg')
                ->addLegacyObject($this);
        }
    }

    /**
     * @return string
     */
    public function toLegacyConfigString()
    {
        if ($this->get('service_set_id') !== null) {
            return '';
        }

        $str = parent::toLegacyConfigString();

        if (
            ! $this->isDisabled()
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

        if (
            ! $this->isDisabled()
            && $this->get('host_id')
            && $this->getRelated('host')->isDisabled()
        ) {
            return "/* --- This services host has been disabled ---\n"
                // Do not allow strings to break our comment
                . str_replace('*/', "* /", $str) . "*/\n";
        } else {
            return $str;
        }
    }

    /**
     * @return string
     */
    protected function renderObjectHeader()
    {
        $applyFor = $this->get('apply_for');
        if (
            $this->isApplyRule()
            && !$this->hasBeenAssignedToHostTemplate()
            && $applyFor !== null
        ) {
            $name = $this->getObjectName();
            $extraName = '';
            $applyForVar = substr($applyFor, strlen('host.vars.'));
            if (preg_match('/[^a-zA-Z0-9_]/', $applyForVar)) {
                // Old escaping missed newlines and other control chars, use the
                // same string renderer as everywhere else in the config
                $applyFor = 'host.vars[' . c::renderString($applyForVar) . ']';
            }

            $propertyType = $this->fetchApplyForPropertyType($applyForVar);
            $isApplyFor = $propertyType === 'dynamic-dictionary';
            $varName = c::renderString($name);

            if (c::stringHasMacro($name)) {
                $extraName = c::renderKeyValue(
                    'name',
                    c::renderStringWithVariables($name, $this->getApplyForVariableWhiteList())
                );
                $name = '';
            } elseif ($name !== '') {
                $name = ' ' . c::renderString($name);
            }

            if ($isApplyFor) {
                $header = "%s %s%s for (key => value in %s) {\n";
            } else {
                $header = "%s %s%s for (value in %s) {\n";
            }

            $extraInfo = $propertyType !== null
                ? sprintf("\n    vars.%s = %s\n", CustomVariables::RESERVED_OVERRIDE_HANDOFF_KEY, $varName)
                : '';

            return sprintf(
                $header,
                $this->getObjectTypeName(),
                $this->getType(),
                $name,
                $applyFor
            )
                . $extraName
                . $extraInfo;
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
     * Fetch property type of the custom variable used in apply for rule
     *
     * @param string $applyFor
     *
     * @return ?string
     */
    protected function fetchApplyForPropertyType(string $applyFor): ?string
    {
        // Data field names and Custom Variable names are independent namespaces.
        // An Apply For rule created long before Custom Variables existed may use a
        // Data field name that a wholly unrelated Custom Variable now happens to
        // share, on some unrelated host template. The Data field must keep winning,
        // or an existing, already-deployed Apply For rule would silently start
        // rendering as a dictionary iteration it was never meant to be.
        if ($this->hasLegacyArrayDatafield($applyFor)) {
            return null;
        }

        // key_name is unique among root properties, a DB constraint enforces it on
        // both engines, so this can only ever match one row. The host_property join
        // just weeds out a key_name nobody has attached anywhere.
        $query = $this->db
            ->select()
            ->from(['dp' => 'director_property'], ['value_type' => 'dp.value_type'])
            ->join(['iop' => 'icinga_host_property'], 'dp.uuid = iop.property_uuid', [])
            ->where('dp.parent_uuid IS NULL')
            ->where('dp.key_name = ?', $applyFor);

        $result = $this->db->fetchOne($query);

        return $result === false ? null : $result;
    }

    /**
     * Whether a deprecated Data field of an array-like type with this name is
     * already attached to some host template
     *
     * @param string $varName
     *
     * @return bool
     */
    protected function hasLegacyArrayDatafield(string $varName): bool
    {
        $query = $this->db
            ->select()
            ->from(['df' => 'director_datafield'], ['varname' => 'df.varname'])
            ->join(['ihf' => 'icinga_host_field'], 'df.id = ihf.datafield_id', [])
            ->where('df.varname = ?', $varName)
            ->where('df.datatype = ?', DataTypeArray::class);

        return (bool) $this->db->fetchOne($query);
    }

    protected function rendersConditionalTemplate(): bool
    {
        return $this->getRenderingZone() === self::ALL_NON_GLOBAL_ZONES;
    }

    /**
     * @return bool
     */
    public function hasBeenAssignedToHostTemplate()
    {
        // Branches would fail
        if ($this->properties['host_id'] === null) {
            return null;
        }
        $hostId = $this->get('host_id');

        return $hostId && $this->getRelatedObject(
            'host',
            $hostId
        )->isTemplate();
    }

    /**
     * @return string
     * @throws \Icinga\Exception\NotFoundError
     * @throws \Icinga\Module\Director\Exception\NestingError
     */
    protected function renderSuffix()
    {
        $suffix = '';
        if ($this->isApplyRule()) {
            $zoneName = $this->getRenderingZone();
            if (!IcingaZone::zoneNameIsGlobal($zoneName, $this->connection)) {
                $suffix .= c::renderKeyValue('zone', c::renderString($zoneName));
            }
        }

        if ($this->isApplyRule() || $this->usesVarOverrides()) {
            $suffix .= $this->renderImportHostVarOverrides();
        }

        return $suffix . parent::renderSuffix();
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
     * @throws \Icinga\Exception\NotFoundError
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
        $blacklistedTemplates = [];
        $blacklistedHosts = [];
        foreach ($blacklist as $hostname) {
            if (IcingaHost::load($hostname, $this->connection)->isTemplate()) {
                $blacklistedTemplates[] = $hostname;
            } else {
                $blacklistedHosts[] = $hostname;
            }
        }
        foreach ($blacklistedTemplates as $template) {
            $output .= sprintf(
                "    ignore where %s in host.templates\n",
                c::renderString($template)
            );
        }
        if (! empty($blacklistedHosts)) {
            if (count($blacklistedHosts) === 1) {
                $output .= sprintf(
                    "    ignore where host.name == %s\n",
                    c::renderString($blacklistedHosts[0])
                );
            } else {
                $output .= sprintf(
                    "    ignore where host.name in %s\n",
                    c::renderArray($blacklistedHosts)
                );
            }
        }

        // A hand-crafted command endpoint overrides use_agent
        if ($this->get('command_endpoint_id') !== null) {
            return $output;
        }

        if ($this->get('use_agent') === 'y') {
            // When feature flag feature_custom_endpoint is enabled, render additional code
            if ($this->connection->settings()->get('feature_custom_endpoint') === 'y') {
                return $output . "
    // Set command_endpoint dynamically with Director
    if (!host) {
        var host = get_host(host_name)
    }
    if (host.vars._director_custom_endpoint_name) {
        command_endpoint = host.vars._director_custom_endpoint_name
    } else {
        command_endpoint = host_name
    }
";
            } else {
                return $output . c::renderKeyValue('command_endpoint', 'host_name');
            }
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
        } elseif ($this->get('service_set_id')) {
            return 'director/serviceset/services?name=' . rawurlencode($this->get('service_set'));
        } else {
            return parent::getOnDeleteUrl();
        }
    }

    protected function getDefaultZone(?IcingaConfig $config = null)
    {
        // Hint: this isn't possible yet, as we're unable to render dependent apply rules to multiple zones as well
        // if ($this->isTemplate()) {
        //     return self::ALL_NON_GLOBAL_ZONES;
        // }
        if ($this->get('host_id') === null) {
            return parent::getDefaultZone();
        } else {
            $zone = $this->getRelatedObject('host', $this->get('host_id'))
                ->getRenderingZone($config);

            // Hint: this avoids problems with host templates rendered to all non-global zones
            if ($zone === self::ALL_NON_GLOBAL_ZONES) {
                $zone = $this->connection->getDefaultGlobalZoneName();
            }

            return $zone;
        }
    }

    /**
     * @return string
     */
    public function createWhere()
    {
        $where = parent::createWhere();
        if (! $this->hasBeenLoadedFromDb()) {
            if (
                null === $this->get('service_set_id')
                && null === $this->get('host_id')
                && null === $this->get('id')
            ) {
                $where .= " AND object_type = 'template'";
            }
        }

        return $where;
    }

    /**
     * Get this service's vars, locked down to the apply-for whitelist
     *
     * An apply-for service can only use specific var paths like value or
     * host.*, everything else stays blocked so nothing leaks through
     *
     * @return CustomVariables
     */
    public function vars()
    {
        $vars = parent::vars();
        if ($this->connection === null || ! $this->isApplyRule()) {
            return $vars;
        }

        $applyFor = substr($this->get('apply_for') ?? '', strlen('host.vars.'));
        if (empty($applyFor)) {
            return $vars;
        }

        if ($this->applyForWhiteList === null) {
            $isApplyForDictionary = $this->fetchApplyForPropertyType($applyFor) === 'dynamic-dictionary';
            $whiteList = ['value', 'host.*'];
            if ($isApplyForDictionary) {
                $whiteList[] = 'key';
                $whiteList[] = 'value[*]';
                $whiteList[] = 'value[*].*';
                $whiteList[] = 'value.*';
            } else {
                // Legacy alias: before the loop variable was renamed, custom variable
                // strings could reference the plain-array apply-for loop value as $config$.
                // Those stored strings survive an upgrade unchanged, so keep resolving them
                // to what is now called "value" instead of silently leaving them literal.
                $whiteList['config'] = 'value';
            }

            $this->applyForWhiteList = $whiteList;
        }

        $vars->setWhiteList($this->applyForWhiteList);

        return $vars;
    }

    protected function getApplyForVariableWhiteList(): ?array
    {
        // building vars also builds this whitelist, reuse it here
        $this->vars();

        return $this->applyForWhiteList;
    }

    /**
     * TODO: Duplicate code, clean this up, split it into multiple methods
     * @param ?Db $connection
     * @param string $prefix
     * @param null $filter
     * @return array
     */
    public static function enumProperties(
        ?Db $connection = null,
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
        if (
            $this->isObject()
            && $this->get('service_set_id') === null
            && $this->get('host_id') === null
        ) {
            throw new InvalidArgumentException(
                'Cannot store a Service object without a related host or set: ' . $this->getObjectName()
            );
        }
    }

    protected function notifyResolvers()
    {
        $resolver = $this->getServiceGroupMembershipResolver();
        $resolver->addObject($this);
        $resolver->refreshDb();

        return $this;
    }

    protected function getServiceGroupMembershipResolver()
    {
        if ($this->servicegroupMembershipResolver === null) {
            $this->servicegroupMembershipResolver = new ServiceGroupMembershipResolver(
                $this->getConnection()
            );
        }

        return $this->servicegroupMembershipResolver;
    }

    public function setServiceGroupMembershipResolver(ServiceGroupMembershipResolver $resolver)
    {
        $this->servicegroupMembershipResolver = $resolver;
        return $this;
    }
}
