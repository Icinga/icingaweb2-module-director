<?php

namespace Icinga\Module\Director\Objects;

use Icinga\Exception\ConfigurationError;
use Icinga\Module\Director\IcingaConfig\IcingaConfigHelper as c;

class IcingaDependency extends IcingaObject
{
    protected $table = 'icinga_dependency';

    protected $defaultProperties = array(
        'id'                    => null,
        'object_name'           => null,
        'object_type'           => null,
        'disabled'              => 'n',
        'apply_to'              => null,
        'parent_host_id'               => null,
        'parent_service_id'            => null,
        'child_host_id'               => null,
        'child_service_id'            => null,
        'disable_checks'              => null,
        'disable_notifications'       => null,
        'ignore_soft_states'          => null,
        'period_id'             => null,
        'zone_id'               => null,
        'assign_filter'         => null,
    );

    protected $supportsCustomVars = false;

    protected $supportsImports = true;

    protected $supportsApplyRules = true;

    protected $relatedSets = array(
        'states' => 'StateFilterSet',
    );

    protected $relations = array(
        'zone'    => 'IcingaZone',
        'parent_host'    => 'IcingaHost',
        'parent_service' => 'IcingaService',
        'child_host'    => 'IcingaHost',
        'child_service' => 'IcingaService',
        'period'  => 'IcingaTimePeriod',
    );

    protected $booleans = array(
        'disable_checks' => 'disable_checks',
        'disable_notifications' => 'disable_notifications',
        'ignore_soft_states' => 'ignore_soft_states'
    );

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
                throw new ConfigurationError(
                    'Applied dependency "%s" has no valid object type',
                    $this->getObjectName()
                );
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

    protected function setKey($key)
    {
        if (is_int($key)) {
            $this->id = $key;
        } elseif (is_array($key)) {
            foreach (array('id', 'parent_host_id', 'parent_service_id', 'child_host_id', 'child_service_id', 'object_name') as $k) {
                if (array_key_exists($k, $key)) {
                    $this->set($k, $key[$k]);
                }
            }
        } else {
            return parent::setKey($key);
        }

        return $this;
    }

    protected function renderAssignments()
    {
        if ($this->hasBeenAssignedToServiceApply()) {

            $tmpService= $this->getRelatedObject('child_service', $this->child_service_id);
            $assigns = $tmpService->assignments()->toConfigString();

            $filter = sprintf(
                '%s && service.name == "%s"', 
                trim($assigns), $this->child_service
            );
            return "\n    " . $filter . "\n";
        }

        if ($this->hasBeenAssignedToHostTemplateService()) {
            $filter = sprintf(
                'assign where "%s" in host.templates && service.name == "%s"',
                $this->child_host, $this->child_service
            );
            return "\n    " . $filter . "\n";
        }
        if ($this->hasBeenAssignedToHostTemplate()) {
            $filter = sprintf(
                'assign where "%s" in host.templates',
                $this->child_host
            );
            return "\n    " . $filter . "\n";
        }
        
        if ($this->hasBeenAssignedToServiceTemplate()) {
            $filter = sprintf(
                'assign where "%s" in service.templates',
                $this->child_service
            );
            return "\n    " . $filter . "\n";
        }
        
        return parent::renderAssignments();
    }

    protected function hasBeenAssignedToHostTemplate()
    {
        return $this->child_host_id && $this->getRelatedObject(
            'child_host',
            $this->child_host_id
        )->object_type === 'template';
    }

    protected function hasBeenAssignedToServiceTemplate()
    {
        return $this->child_service_id && $this->getRelatedObject(
            'child_service',
            $this->child_service_id
        )->object_type === 'template';
    }

    protected function hasBeenAssignedToHostTemplateService()
    {
        if (!$this->hasBeenAssignedToHostTemplate()) return false;
        return $this->child_service_id && $this->getRelatedObject(
            'child_service',
            $this->child_service_id
        )->object_type === 'object';
    }

    protected function hasBeenAssignedToServiceApply()
    {
        return $this->child_service_id && $this->getRelatedObject(
            'child_service',
            $this->child_service_id
        )->object_type === 'apply';
    }


    /**
     * Render child_host_id as host_name
     *
     * Avoid complaints for method names with underscore:
     * @codingStandardsIgnoreStart
     *
     * @return string
     */
    public function renderChild_host_id()
    {
        // @codingStandardsIgnoreEnd

        if ($this->hasBeenAssignedToHostTemplate()) {
            return '';
        }

        return $this->renderRelationProperty('child_host', $this->child_host_id, 'child_host_name');
    }

    /**
     * Render parent_host_id as parent_host_name
     *
     * Avoid complaints for method names with underscore:
     * @codingStandardsIgnoreStart
     *
     * @return string
     */
    public function renderParent_host_id()
    {
        // @codingStandardsIgnoreEnd

        return $this->renderRelationProperty('parent_host', $this->parent_host_id, 'parent_host_name');
    }


    /**
     * Render child_service_id as host_name
     *
     * Avoid complaints for method names with underscore:
     * @codingStandardsIgnoreStart
     *
     * @return string
     */
    public function renderChild_service_id()
    {
        // @codingStandardsIgnoreEnd
        if ($this->hasBeenAssignedToServiceTemplate()) {
            return '';
        }

        if ($this->hasBeenAssignedToHostTemplateService()) {
            return '';
        }

        if ($this->hasBeenAssignedToServiceApply()) {
            return '';
        }

        return $this->renderRelationProperty('child_service', $this->child_service_id, 'child_service_name');
    }

    /**
     * Render parent_service_id as parent_service_name
     *
     * Avoid complaints for method names with underscore:
     * @codingStandardsIgnoreStart
     *
     * @return string
     */
    public function renderParent_service_id()
    {
            return $this->renderRelationProperty('parent_service', $this->parent_service_id, 'parent_service_name');
    }


    public function isApplyRule()
    {
        if ($this->hasBeenAssignedToHostTemplate()) {
            return true;
        }

        if ($this->hasBeenAssignedToServiceTemplate()) {
            return true;
        }

        if ($this->hasBeenAssignedToServiceApply()) {
            return true;
        }

        return $this->hasProperty('object_type')
            && $this->object_type === 'apply';
    }




}
