<?php

namespace Icinga\Module\Director\Objects;

class IcingaHostGroup extends IcingaObjectGroup
{
    protected $table = 'icinga_hostgroup';

    protected $defaultProperties = [
        'id'            => null,
        'uuid'          => null,
        'object_name'   => null,
        'object_type'   => null,
        'disabled'      => 'n',
        'display_name'  => null,
        'assign_filter' => null,
        'zone_id'       => null,
    ];

    protected $relations = [
        'zone' => 'IcingaZone',
    ];

    /** @var HostGroupMembershipResolver */
    protected $hostgroupMembershipResolver;

    public function supportsAssignments()
    {
        return true;
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

    protected function getMemberShipResolver()
    {
        return $this->getHostGroupMembershipResolver();
    }
}
