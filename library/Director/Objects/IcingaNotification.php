<?php

namespace Icinga\Module\Director\Objects;

use Icinga\Module\Director\CustomVariable\CustomVariables;
use Icinga\Module\Director\DirectorObject\Automation\ExportInterface;
use Icinga\Module\Director\IcingaConfig\IcingaConfigHelper as c;
use RuntimeException;

class IcingaNotification extends IcingaObject implements ExportInterface
{
    protected $table = 'icinga_notification';

    protected $defaultProperties = [
        'id'                    => null,
        'uuid'                  => null,
        'object_name'           => null,
        'object_type'           => null,
        'disabled'              => 'n',
        'apply_to'              => null,
        'host_id'               => null,
        'service_id'            => null,
        // 'users'                 => null,
        // 'user_groups'           => null,
        'times_begin'           => null,
        'times_end'             => null,
        'command_id'            => null,
        'notification_interval' => null,
        'period_id'             => null,
        'zone_id'               => null,
        'users_var'             => null,
        'user_groups_var'       => null,
        'assign_filter'         => null,
    ];

    protected $uuidColumn = 'uuid';

    protected $supportsCustomVars = true;

    protected $supportsFields = true;

    protected $supportsCustomProperties = true;

    protected $supportsImports = true;

    protected $supportsApplyRules = true;

    protected $relatedSets = [
        'states' => 'StateFilterSet',
        'types'  => 'TypeFilterSet',
    ];

    protected $multiRelations = [
        'users'       => 'IcingaUser',
        'user_groups' => 'IcingaUserGroup',
    ];

    protected $relations = [
        'zone'    => 'IcingaZone',
        'host'    => 'IcingaHost',
        'service' => 'IcingaService',
        'command' => 'IcingaCommand',
        'period'  => 'IcingaTimePeriod',
    ];

    protected $intervalProperties = [
        'notification_interval' => 'interval',
        'times_begin'           => 'times_begin',
        'times_end'             => 'times_end',
    ];

    protected function prefersGlobalZone()
    {
        return false;
    }

    /**
     * @codingStandardsIgnoreStart
     * @return string
     */
    protected function renderTimes_begin()
    {
        // @codingStandardsIgnoreEnd
        return c::renderKeyValue('times.begin', c::renderInterval($this->get('times_begin')));
    }

    /**
     * @codingStandardsIgnoreStart
     * @return string
     */
    protected function renderUsers_var()
    {
        // @codingStandardsIgnoreEnd
        return '';
    }

    /**
     * @codingStandardsIgnoreStart
     * @return string
     */
    protected function renderUser_groups_var()
    {
        // @codingStandardsIgnoreEnd
        return '';
    }

    protected function renderUserVarsSuffixFor($property)
    {
        $varName = $this->getResolvedProperty("{$property}_var");
        if ($varName === null) {
            return '';
        }

        $varSuffix = CustomVariables::renderKeySuffix($varName);
        $indent = '    ';
        $objectType = $this->get('apply_to');
        if ($objectType === 'service') {
            return "{$indent}if (service.vars$varSuffix) {\n"
                . c::renderKeyOperatorValue($property, '+=', "service.vars$varSuffix", $indent . '    ')
                . "$indent} else {\n"
                . $this->getHostSnippet($indent . '    ')
                . "$indent    if (host.vars$varSuffix) { "
                . c::renderKeyOperatorValue($property, '+=', "host.vars$varSuffix }", '')
                . "$indent}\n";
        } elseif ($objectType === 'host') {
            return $this->getHostSnippet()
                . "{$indent}if (host.vars$varSuffix) { "
                . c::renderKeyOperatorValue($property, '+=', "host.vars$varSuffix }");
        }

        return '';
    }

    protected function getHostSnippet($indent = '    ')
    {
        return "{$indent}if (! host) {\n"
            . "$indent    var host = get_host(host_name)\n"
            . "$indent}\n";
    }

    protected function renderSuffix()
    {
        return $this->renderUserVarsSuffixFor('users')
            . $this->renderUserVarsSuffixFor('user_groups')
            . parent::renderSuffix();
    }

    /**
     * @codingStandardsIgnoreStart
     * @return string
     */
    protected function renderTimes_end()
    {
        // @codingStandardsIgnoreEnd
        return c::renderKeyValue('times.end', c::renderInterval($this->get('times_end')));
    }

    public function getUniqueIdentifier()
    {
        return $this->getObjectName();
    }

    /**
     * Do not render internal property apply_to
     *
     * Avoid complaints for method names with underscore:
     * @codingStandardsIgnoreStart
     *
     * @return string
     */
    public function renderApply_to()
    {
        // @codingStandardsIgnoreEnd
        return '';
    }

    protected function renderObjectHeader()
    {
        if ($this->isApplyRule()) {
            if (($to = $this->get('apply_to')) === null) {
                throw new RuntimeException(sprintf(
                    'No "apply_to" object type has been set for Applied notification "%s"',
                    $this->getObjectName()
                ));
            }

            return sprintf(
                "%s %s %s to %s {\n",
                $this->getObjectTypeName(),
                $this->getType(),
                c::renderString($this->getObjectName()),
                ucfirst($to)
            );
        } else {
            return parent::renderObjectHeader();
        }
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
        return $this->renderRelationProperty('host', $this->get('host_id'), 'host_name');
    }

    /**
     * Render service_id as service_name
     *
     * @codingStandardsIgnoreStart
     * @return string
     */
    public function renderService_id()
    {
        // @codingStandardsIgnoreEnd
        return $this->renderRelationProperty('service', $this->get('service_id'), 'service_name');
    }

    protected function setKey($key)
    {
        if (is_int($key)) {
            $this->id = $key;
        } elseif (is_array($key)) {
            foreach (['id', 'host_id', 'service_id', 'object_name'] as $k) {
                if (array_key_exists($k, $key)) {
                    $this->set($k, $key[$k]);
                }
            }
        } else {
            return parent::setKey($key);
        }

        return $this;
    }
}
