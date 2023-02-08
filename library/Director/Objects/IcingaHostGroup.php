<?php

namespace Icinga\Module\Director\Objects;

class IcingaHostGroup extends IcingaObjectGroup
{
    protected $table = 'icinga_hostgroup';

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
                $this->getConnection(), 'hostgroup'
            );
        }

        return $this->hostgroupMembershipResolver;
    }

    public function setHostGroupMembershipResolver(HostGroupMembershipResolver $resolver)
    {
        $this->hostgroupMembershipResolver = $resolver;
        return $this;
    }

    protected function notifyResolvers()
    {
        $resolver = $this->getHostGroupMembershipResolver();
        $resolver->addGroup($this);
        $resolver->refreshDb();

        return $this;
    }
}
