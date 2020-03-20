<?php

namespace Icinga\Module\Director\Restriction;

use Icinga\Exception\ProgrammingError;
use Icinga\Module\Director\Db\IcingaObjectFilterHelper;
use Icinga\Module\Director\Objects\IcingaHost;
use Icinga\Module\Director\Objects\IcingaHostGroup;
use Icinga\Module\Director\Objects\IcingaObject;
use Zend_Db_Select as ZfSelect;

class HostgroupRestriction extends ObjectRestriction
{
    protected $name = 'director/filter/hostgroups';

    public function allows(IcingaObject $object)
    {
        if ($object instanceof IcingaHost) {
            return $this->allowsHost($object);
        } elseif ($object instanceof IcingaHostGroup) {
            return $this->allowsHostGroup($object);
        } else {
            return $this;
        }
    }

    protected function filterQuery(ZfSelect $query, $tableAlias = 'o')
    {
        $table = $this->getQueryTableByAlias($query, $tableAlias);
        switch ($table) {
            case 'icinga_host':
                $this->filterHostsQuery($query, $tableAlias);
                break;
            case 'icinga_service':
                // TODO: Alias is hardcoded
                $this->filterHostsQuery($query, 'h');
                break;
            case 'icinga_hostgroup':
                $this->filterHostGroupsQuery($query, $tableAlias);
                break;
            // Hint: other tables are ignored, so please take care!
        }

        return $query;
    }

    /**
     * Whether access to the given host is allowed
     *
     * @param IcingaHost $host
     * @return bool
     */
    public function allowsHost(IcingaHost $host)
    {
        if (! $this->isRestricted()) {
            return true;
        }

        if (! $host->hasBeenLoadedFromDb()) {
            foreach ($this->listRestrictedHostgroups() as $group) {
                if ($host->hasGroup($group)) {
                    return true;
                }
            }

            return false;
        }

        $query = $this->db->select()->from(
            ['o' => 'icinga_host'],
            ['id']
        )->where('o.id = ?', $host->id);

        $this->filterHostsQuery($query);
        return (int) $this->db->fetchOne($query) === (int) $host->get('id');
    }

    /**
     * Whether access to the given hostgroup is allowed
     *
     * @param IcingaHostGroup $hostgroup
     * @return bool
     */
    public function allowsHostGroup(IcingaHostGroup $hostgroup)
    {
        if (! $this->isRestricted()) {
            return true;
        }

        $query = $this->db->select()->from(
            ['h' => 'icinga_hostgroup'],
            ['id']
        )->where('id = ?', $hostgroup->id);

        $this->filterHostGroupsQuery($query);
        return (int) $this->db->fetchOne($query) === (int) $hostgroup->get('id');
    }

    /**
     * Apply the restriction to the given Hosts Query
     *
     * We assume that the query wants to fetch hosts and that therefore the
     * icinga_host table already exists in the given query, using the $tableAlias
     * alias.
     *
     * @param ZfSelect $query
     * @param string $tableAlias
     */
    public function filterHostsQuery(ZfSelect $query, $tableAlias = 'o')
    {
        if (! $this->isRestricted()) {
            return;
        }

        IcingaObjectFilterHelper::filterByResolvedHostgroups(
            $query,
            'host',
            $this->listRestrictedHostgroups(),
            $tableAlias
        );
    }

    /**
     * Apply the restriction to the given Hosts Query
     *
     * We assume that the query wants to fetch hosts and that therefore the
     * icinga_host table already exists in the given query, using the $tableAlias
     * alias.
     *
     * @param ZfSelect $query
     * @param string $tableAlias
     */
    protected function filterHostGroupsQuery(ZfSelect $query, $tableAlias = 'o')
    {
        if (! $this->isRestricted()) {
            return;
        }
        $groups = $this->listRestrictedHostgroups();

        if (empty($groups)) {
            $query->where('(1 = 0)');
        } else {
            $query->where("${tableAlias}.object_name IN (?)", $groups);
        }
    }

    /**
     * Give a list of allowed Hostgroups
     *
     * When not restricted, null is returned. This might eventually also give
     * an empty list, and therefore not allow any access at all
     *
     * @return array|null
     */
    protected function listRestrictedHostgroups()
    {
        if ($restrictions = $this->auth->getRestrictions($this->getName())) {
            $groups = array();
            foreach ($restrictions as $restriction) {
                foreach ($this->gracefullySplitOnComma($restriction) as $group) {
                    $groups[$group] = $group;
                }
            }

            return array_keys($groups);
        } else {
            return null;
        }
    }
}
