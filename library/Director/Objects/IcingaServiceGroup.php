<?php

namespace Icinga\Module\Director\Objects;

class IcingaServiceGroup extends IcingaObjectGroup
{
    protected $table = 'icinga_servicegroup';

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

    /** @var ServiceGroupMembershipResolver */
    protected $servicegroupMembershipResolver;

    public function supportsAssignments()
    {
        return true;
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

    protected function getMemberShipResolver()
    {
        return $this->getServiceGroupMembershipResolver();
    }
}
