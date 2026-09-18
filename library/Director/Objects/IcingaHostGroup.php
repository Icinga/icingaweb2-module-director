<?php

// SPDX-FileCopyrightText: 2018 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

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
