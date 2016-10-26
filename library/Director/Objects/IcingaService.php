<?php

namespace Icinga\Module\Director\Objects;

use Icinga\Data\Filter\Filter;
use Icinga\Exception\ProgrammingError;
use Icinga\Module\Director\IcingaConfig\IcingaConfig;
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
        'apply_for'             => null,
        'use_var_overrides'     => null,
        'assign_filter'         => null,
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
        'use_var_overrides'     => 'use_var_overrides',
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

    protected $supportsSets = true;

    protected $keyName = array('host_id', 'object_name');

    protected $prioritizedProperties = array('host_id');

    protected $propertiesNotForRendering = array(
        'id',
        'object_name',
        'object_type',
        'apply_for'
    );

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

    public function usesVarOverrides()
    {
        return $this->use_var_overrides === 'y';
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

        return $this->renderRelationProperty('host', $this->host_id, 'host_name');
    }

    protected function renderObjectHeader()
    {
        if ($this->isApplyRule()
            && !$this->hasBeenAssignedToHostTemplate()
            && $this->get('apply_for') !== null) {

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

    protected function hasBeenAssignedToHostTemplate()
    {
        return $this->host_id && $this->getRelatedObject(
            'host',
            $this->host_id
        )->object_type === 'template';
    }

    protected function renderSuffix()
    {
        if ($this->isApplyRule() || $this->usesVarOverrides()) {
            return $this->renderImportHostVarOverrides() . parent::renderSuffix();
        } else {
            return parent::renderSuffix();
        }
    }

    protected function renderImportHostVarOverrides()
    {
        if (! $this->connection) {
            throw new ProgrammingError(
                'Cannot render services without an assigned DB connection'
            );
        }

        return "\n    import DirectorOverrideTemplate\n";
    }

    protected function renderCustomExtensions()
    {
        $output = '';

         if ($this->hasBeenAssignedToHostTemplate()) {
            // TODO: use assignment renderer?
            $filter = sprintf(
                'assign where %s in host.templates',
                c::renderString($this->host)
            );

            $output .= "\n    " . $filter . "\n";
        }

       // A hand-crafted command endpoint overrides use_agent
        if ($this->command_endpoint_id !== null) {
            return $output;
        }

        // In case use_agent isn't defined, do nothing
        // TODO: what if we inherit use_agent and override it with 'n'?
        if ($this->use_agent !== 'y') {
            return $output;
        }

        return $output . c::renderKeyValue('command_endpoint', 'host_name');
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

    public function getRenderingZone(IcingaConfig $config = null)
    {
        if ($this->prefersGlobalZone()) {
            return $this->connection->getDefaultGlobalZoneName();
        }

        $zone = parent::getRenderingZone($config);

        // if bound to a host, and zone is fallback to master
        if ($this->host_id !== null && $zone === $this->connection->getMasterZoneName()) {
            /** @var IcingaHost $host */
            $host = $this->getRelatedObject('host', $this->host_id);
            return $host->getRenderingZone($config);
        }
        return $zone;
    }
}
