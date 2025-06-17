<?php

namespace Icinga\Module\Director\Objects;

class IcingaUserGroup extends IcingaObjectGroup
{
    protected $table = 'icinga_usergroup';

    protected $uuidColumn = 'uuid';

    /** @var UserGroupMembershipResolver */
    protected $userGroupMembershipResolver;

    protected $defaultProperties = [
        'id'            => null,
        'uuid'          => null,
        'object_name'   => null,
        'object_type'   => null,
        'disabled'      => 'n',
        'display_name'  => null,
        'zone_id'       => null,
        'assign_filter' => null
    ];

    protected $relations = [
        'zone' => 'IcingaZone',
    ];

    protected function prefersGlobalZone()
    {
        return false;
    }

    public function supportsAssignments(): bool
    {
        return true;
    }

    /**
     * Set the membership resolver for the user group.
     *
     * @param UserGroupMembershipResolver $resolver
     *
     * @return $this
     */
    public function setUserGroupMembershipResolver(UserGroupMembershipResolver $resolver): static
    {
        $this->userGroupMembershipResolver = $resolver;

        return $this;
    }

    /**
     * Get the membership resolver for the user group.
     *
     * @return UserGroupMembershipResolver
     */
    protected function getMemberShipResolver(): UserGroupMembershipResolver
    {
        if ($this->userGroupMembershipResolver === null) {
            $this->userGroupMembershipResolver = new UserGroupMembershipResolver(
                $this->getConnection()
            );
        }

        return $this->userGroupMembershipResolver;
    }
}
