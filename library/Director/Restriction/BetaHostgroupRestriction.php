<?php

namespace Icinga\Module\Director\Restriction;

use Icinga\Module\Director\Objects\IcingaHost;
use Zend_Db_Select as ZfSelect;

class BetaHostgroupRestriction extends ObjectRestriction
{
    protected $name = 'director/beta-filter/hostgroups';

    public function allowsHost(IcingaHost $host)
    {
        if (! $this->isRestricted()) {
            return true;
        }

        $query = $this->db->select()->from(
            array('h' => 'icinga_host'),
            array('id')
        )->where('id = ?', $host->id);

        $this->applyToHostsQuery($query);
        return (int) $this->db->fetchOne($query) === (int) $host->get('id');
    }

    public function applyToHostsQuery(ZfSelect $query, $hostIdColumn = 'h.id')
    {
        if (! $this->isRestricted()) {
            return;
        }
        $groups = $this->listRestrictedHostgroups();

        if (empty($groups)) {
            $query->where('(1 = 0)');
        } else {
            $sub = $this->db->select()->from(
                array('hgh' => 'icinga_hostgroup_host_resolved'),
                array('e' => '(1)')
            )->join(
                array('hg' => 'icinga_hostgroup'),
                'hgh.hostgroup_id = hg.id'
            )->where('hgh.host_id = ' . $hostIdColumn)
                ->where('hg.object_name IN (?)', $groups);

            $query->where('EXISTS ?', $sub);
        }
    }

    public function applyToHostGroupsQuery(ZfSelect $query)
    {
        if (! $this->isRestricted()) {
            return;
        }
        $groups = $this->listRestrictedHostgroups();

        if (empty($groups)) {
            $query->where('(1 = 0)');
        } else {
            $query->where('object_name IN (?)', $groups);
        }
    }

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
