<?php

namespace Icinga\Module\Director\Web\Table;

use Icinga\Data\DataArray\ArrayDatasource;
use Icinga\Data\Filter\Filter;
use Icinga\Module\Director\Objects\HostApplyMatches;
use Icinga\Module\Director\Objects\IcingaHost;
use dipl\Html\Link;
use dipl\Web\Table\SimpleQueryBasedTable;

class IcingaHostAppliedServicesTable extends SimpleQueryBasedTable
{
    protected $title;

    /** @var IcingaHost */
    protected $host;

    /** @var \Zend_Db_Adapter_Abstract */
    protected $db;

    private $allApplyRules;

    /**
     * @param IcingaHost $host
     * @return static
     */
    public static function load(IcingaHost $host)
    {
        $table = (new static())->setHost($host);
        $table->getAttributes()->set('data-base-target', '_self');
        return $table;
    }

    public function setTitle($title)
    {
        $this->title = $title;
        return $this;
    }

    public function getColumnsToBeRendered()
    {
        return [$this->title];
    }

    public function setHost(IcingaHost $host)
    {
        $this->host = $host;
        $this->db = $host->getDb();
        return $this;
    }

    public function renderRow($row)
    {
        if ($row->blacklisted === 'y') {
            $attributes = ['class' => 'strike-links'];
        } else {
            $attributes = null;
        }

        return $this::row([
            Link::create(
                sprintf(
                    $this->translate('%s (where %s)'),
                    $row->name,
                    $row->filter
                ),
                'director/host/appliedservice',
                [
                    'name'       => $this->host->getObjectName(),
                    'service_id' => $row->id,
                ]
            )
        ], $attributes);
    }

    /**
     * @return \Icinga\Data\SimpleQuery
     */
    public function prepareQuery()
    {
        $services = [];
        $matcher = HostApplyMatches::prepare($this->host);
        foreach ($this->getAllApplyRules() as $rule) {
            if ($matcher->matchesFilter($rule->filter)) {
                $services[] = $rule;
            }
        }

        $ds = new ArrayDatasource($services);
        return $ds->select()->columns([
            'id'            => 'id',
            'name'          => 'name',
            'filter'        => 'filter',
            'blacklisted'   => 'blacklisted',
            'assign_filter' => 'assign_filter',
        ]);
    }

    /***
     * @return array
     */
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

    /**
     * @return array
     */
    protected function fetchAllApplyRules()
    {
        $db = $this->db;
        $query = $db->select()->from(
            ['s' => 'icinga_service'],
            [
                'id'            => 's.id',
                'name'          => 's.object_name',
                'assign_filter' => 's.assign_filter',
                'blacklisted'   => "CASE WHEN hsb.service_id IS NULL THEN 'n' ELSE 'y' END",
            ]
        )->joinLeft(
            ['hsb' => 'icinga_host_service_blacklist'],
            $db->quoteInto('s.id = hsb.service_id AND hsb.host_id = ?', $this->host->get('id')),
            []
        )->group('s.id')->where('object_type = ? AND assign_filter IS NOT NULL', 'apply');

        return $db->fetchAll($query);
    }
}
