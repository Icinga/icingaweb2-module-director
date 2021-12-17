<?php

namespace Icinga\Module\Director\Web\Table;

use ipl\Html\Html;
use Icinga\Data\DataArray\ArrayDatasource;
use Icinga\Data\Filter\Filter;
use Icinga\Exception\IcingaException;
use Icinga\Module\Director\IcingaConfig\AssignRenderer;
use Icinga\Module\Director\Objects\HostApplyMatches;
use Icinga\Module\Director\Objects\IcingaHost;
use gipfl\IcingaWeb2\Link;
use gipfl\IcingaWeb2\Table\SimpleQueryBasedTable;

class IcingaHostAppliedServicesTable extends SimpleQueryBasedTable
{
    protected $title;

    /** @var IcingaHost */
    protected $host;

    /** @var \Zend_Db_Adapter_Abstract */
    protected $db;

   /** @var bool */
    protected $readonly = false;

    /** @var string|null */
    protected $highlightedService;

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

    /**
     * Show no related links
     *
     * @param bool $readonly
     * @return $this
     */
    public function setReadonly($readonly = true)
    {
        $this->readonly = (bool) $readonly;

        return $this;
    }

    public function highlightService($service)
    {
        $this->highlightedService = $service;

        return $this;
    }

    public function renderRow($row)
    {
        $classes = [];
        if ($row->blacklisted === 'y') {
            $classes[] = 'strike-links';
        }
        if ($row->disabled === 'y') {
            $classes[] = 'disabled';
        }

        $attributes = empty($classes) ? null : ['class' => $classes];

        if ($this->readonly) {
            if ($this->highlightedService === $row->name) {
                $link = Html::tag('a', ['class' => 'icon-right-big'], $row->name);
            } else {
                $link = Html::tag('a', $row->name);
            }
        } else {
            $applyFor = '';
            if (! empty($row->apply_for)) {
                $applyFor = sprintf('(apply for %s) ', $row->apply_for);
            }

            $link = Link::create(sprintf(
                $this->translate('%s %s(%s)'),
                $row->name,
                $applyFor,
                $this->renderApplyFilter($row->filter)
            ), 'director/host/appliedservice', [
                'name'       => $this->host->getObjectName(),
                'service_id' => $row->id,
            ]);
        }

        return $this::row([$link], $attributes);
    }

    /**
     * @param Filter $assignFilter
     *
     * @return string
     */
    protected function renderApplyFilter(Filter $assignFilter)
    {
        try {
            $string = AssignRenderer::forFilter($assignFilter)->renderAssign();
        } catch (IcingaException $e) {
            $string = 'Error in Filter rendering: ' . $e->getMessage();
        }

        return $string;
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
            'disabled'      => 'disabled',
            'blacklisted'   => 'blacklisted',
            'assign_filter' => 'assign_filter',
            'apply_for'     => 'apply_for',
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
        $hostId = $this->host->get('id');
        $query = $db->select()->from(
            ['s' => 'icinga_service'],
            [
                'id'            => 's.id',
                'name'          => 's.object_name',
                'assign_filter' => 's.assign_filter',
                'apply_for'     => 's.apply_for',
                'disabled'      => 's.disabled',
                'blacklisted'   => $hostId ? "CASE WHEN hsb.service_id IS NULL THEN 'n' ELSE 'y' END" : "('n')",
            ]
        )->where('object_type = ? AND assign_filter IS NOT NULL', 'apply')
         ->order('s.object_name');
        if ($hostId) {
            $query->joinLeft(
                ['hsb' => 'icinga_host_service_blacklist'],
                $db->quoteInto('s.id = hsb.service_id AND hsb.host_id = ?', $hostId),
                []
            );
        }

        return $db->fetchAll($query);
    }
}
