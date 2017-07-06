<?php

namespace Icinga\Module\Director\Tables;

use Icinga\Data\DataArray\ArrayDatasource;
use Icinga\Data\Filter\Filter;
use Icinga\Module\Director\Objects\HostApplyMatches;
use Icinga\Module\Director\Objects\IcingaHost;
use Icinga\Module\Director\Web\Table\QuickTable;

class IcingaHostAppliedServicesTable extends QuickTable
{
    protected $title;

    protected $host;

    private $allApplyRules;

    private $baseQuery;

    public function getColumns()
    {
        return array(
            'id'            => 'id',
            'name'          => 'name',
            'assign_filter' => 'assign_filter',
        );
    }

    public function setTitle($title)
    {
        $this->title = $title;
        return $this;
    }

    public function getTitles()
    {
        $view = $this->view();
        return array(
            'label' => $this->title
        );
    }

    public function count()
    {
        return $this->getBaseQuery()->count();
    }

    public function setHost(IcingaHost $host)
    {
        $this->host = $host;
        return $this;
    }

    protected function getActionUrl($row)
    {
        $params = array(
            'name'       => $this->host->object_name,
            'service_id' => $row->id,
        );

        return $this->url('director/host/appliedservice', $params);
    }

    protected function renderRow($row)
    {
        $row->label = sprintf(
            $this->view()->translate('%s (where %s)'),
            $row->name,
            $row->filter
        );

        return parent::renderRow($row);
    }

    public function fetchData()
    {
        return $this->getBaseQuery()->fetchAll();
    }

    public function getBaseQuery()
    {
        if ($this->baseQuery === null) {
            $services = array();
            $matcher = HostApplyMatches::prepare($this->host);
            foreach ($this->getAllApplyRules() as $rule) {
                if ($matcher->matchesFilter($rule->filter)) {
                    $services[] = $rule;
                }
            }

            $ds = new ArrayDatasource($services);
            $this->baseQuery = $ds->select();
        }

        return $this->baseQuery;
    }

    protected function getAllApplyRules()
    {
        if ($this->allApplyRules === null) {
            $this->allApplyRules = $this->fetchAllApplyRules();
            foreach ($this->allApplyRules as $rule) {
                $rule->filter = Filter::fromQueryString($rule->assign_filter);
            }
        }

        return $this->allApplyRules;
    }

    protected function fetchAllApplyRules()
    {
        $db = $this->db();
        $query = $db->select()->from(
            array('s' => 'icinga_service'),
            array(
                'id'            => 's.id',
                'name'          => 's.object_name',
                'assign_filter' => 's.assign_filter',
            )
        )->where('object_type = ? AND assign_filter IS NOT NULL', 'apply');

        return $db->fetchAll($query);
    }
}
