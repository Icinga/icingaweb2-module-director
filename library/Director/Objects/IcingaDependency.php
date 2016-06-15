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
}
