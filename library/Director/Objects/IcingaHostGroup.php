<?php

namespace Icinga\Module\Director\Objects;

use Icinga\Data\Filter\Filter;
use Icinga\Exception\ProgrammingError;
use Icinga\Module\Director\IcingaConfig\IcingaConfig;
use Icinga\Module\Director\IcingaConfig\IcingaLegacyConfigHelper as c1;

class IcingaHostGroup extends IcingaObjectGroup
{
    protected $table = 'icinga_hostgroup';

    public function supportsAssignments()
    {
        return true;
    }

    public function renderToLegacyConfig(IcingaConfig $config)
    {
        if ($this->get('assign_filter') !== null) {
            $this->renderLegacyApplyToConfig($config);
        } else {
            parent::renderToLegacyConfig($config);
        }
    }

    /**
     * @param  IcingaConfig  $config
     *
     * @throws ProgrammingError  When IcingaConfig deployment mode is not supported
     */
    protected function renderLegacyApplyToConfig(IcingaConfig $config)
    {
        $conn = $this->getConnection();

        $filter = Filter::fromQueryString($this->get('assign_filter'));
        $hosts = HostApplyMatches::forFilter($filter, $conn);
        $this->set('object_type', 'object');

        $zoneMap = array();

        foreach ($hosts as $hostname) {
            $host = IcingaHost::load($hostname, $this->connection);

            if (($zoneId = $host->getResolvedProperty('zone_id')) !== null) {
                $zoneMap[$zoneId][] = $hostname;
            } else {
                $zoneMap[0][] = $hostname;
            }
        }

        if (empty($zoneMap)) {
            // no hosts matched
            $file = $this->legacyZoneHostgroupFile($config);
            $this->properties['hostgroup_members'] = array();
            $file->addLegacyObject($this);

        } else {
            $allMembers = array();

            foreach ($zoneMap as $zoneId => $members) {
                $file = $this->legacyZoneHostgroupFile($config, $zoneId);
                $this->properties['hostgroup_members'] = $members;
                $file->addLegacyObject($this);

                $allMembers = array_merge($allMembers, $members);
            }

            $deploymentMode = $config->getDeploymentMode();
            if ($deploymentMode === 'active-passive') {
                $this->properties['hostgroup_members'] = $allMembers;
                $this->legacyZoneHostgroupFile($config, 0)
                    ->addLegacyObject($this);
            } else if ($deploymentMode == 'masterless') {
                // nothing to add
            } else {
                throw new ProgrammingError('Unsupported deployment mode: %s' . $deploymentMode);
            }
        }
    }

    protected function legacyZoneHostgroupFile(IcingaConfig $config, $zoneId = null)
    {
        if ($zoneId !== null) {
            $zone = IcingaZone::load($zoneId, $this->getConnection())->getObjectName();
        } else {
            $zone = $this->connection->getDefaultGlobalZoneName();
        }
        return $config->configFile(
            'director/' . $zone . '/hostgroups', '.cfg'
        );
    }

    protected function renderLegacyHostgroup_members()
    {
        if (empty($this->properties['hostgroup_members'])) {
            return '';
        }
        return c1::renderKeyValue('hostgroup_members', join(',', $this->properties['hostgroup_members']));
    }

    /**
     * Note: rendered with renderLegacyHostgroup_members()
     *
     * @return string
     */
    protected function renderLegacyAssign_filter()
    {
        return '';
    }
}
