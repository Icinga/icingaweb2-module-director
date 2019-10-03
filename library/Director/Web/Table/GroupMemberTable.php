<?php

namespace Icinga\Module\Director\Web\Table;

use gipfl\IcingaWeb2\Table\Extension\MultiSelect;
use Icinga\Data\Filter\Filter;
use Icinga\Module\Director\Db;
use Icinga\Module\Director\IcingaConfig\AssignRenderer;
use Icinga\Module\Director\Objects\IcingaObjectGroup;
use Exception;
use gipfl\IcingaWeb2\Link;
use gipfl\IcingaWeb2\Table\ZfQueryBasedTable;
use gipfl\IcingaWeb2\Url;

class GroupMemberTable extends ZfQueryBasedTable
{
    use MultiSelect;

    protected $searchColumns = [
        'o.object_name',
        // membership_type
    ];

    protected $type;

    /** @var IcingaObjectGroup */
    protected $group;

    /**
     * @param $type
     * @param Db $db
     * @return static
     */
    public static function create($type, Db $db)
    {
        $class = __NAMESPACE__ . '\\GroupMemberTable' . ucfirst($type);
        if (! class_exists($class)) {
            $class = __CLASS__;
        }

        /** @var static $table */
        $table = new $class($db);
        $table->type = $type;
        return $table;
    }
    public function assemble()
    {
        if ($this->type === 'host') {
            $this->enableMultiSelect(
                'director/hosts/edit',
                'director/hosts',
                ['name']
            );
        }
    }

    public function setGroup(IcingaObjectGroup $group)
    {
        $this->group = $group;
        return $this;
    }

    public function getType()
    {
        return $this->type;
    }

    public function getColumnsToBeRendered()
    {
        if ($this->group === null) {
            return [
                $this->translate('Group'),
                $this->translate('Member'),
                $this->translate('via')
            ];
        } else {
            return [
                $this->translate('Member'),
                $this->translate('via')
            ];
        }
    }

    public function renderRow($row)
    {
        $type = $this->getType();
        if ($row->object_type === 'apply') {
            $params = [
                'id' => $row->id
            ];
        } elseif (isset($row->host_id)) {
            // I would prefer to see host=<name> and set=<name>, but joining
            // them here is pointless. We should use DeferredHtml for these,
            // remember hosts/sets we need and fetch them in a single query at
            // rendering time. For now, this works fine - just... the URLs are
            // not so nice
            $params = [
                'name' => $row->object_name,
                'host_id' => $row->host_id
            ];
        } elseif (isset($row->service_set_id)) {
            $params = [
                'name' => $row->object_name,
                'set_id' => $row->service_set_id
            ];
        } else {
            $params = [
                'name' => $row->object_name
            ];
        }

        $url = Url::fromPath("director/${type}", $params);

        $tr = $this::tr();

        if ($this->group === null) {
            $tr->add($this::td($row->group_name));
        }
        $link = Link::create($row->object_name, $url);
        if ($row->object_type === 'apply') {
            $link = [
                $link,
                ' (where ',
                $this->renderApplyFilter($row->assign_filter),
                ')'
            ];
        }

        $tr->add([
            $this::td($link),
            $this::td($row->membership_type)
        ]);

        return $tr;
    }

    protected function renderApplyFilter($assignFilter)
    {
        try {
            $string = AssignRenderer::forFilter(
                Filter::fromQueryString($assignFilter)
            )->renderAssign();
            // Do not prefix it
            $string = preg_replace('/^assign where /', '', $string);
        } catch (Exception $e) {
            // ignore errors in filter rendering
            $string = 'Error in Filter rendering: ' . $e->getMessage();
        }

        return $string;
    }

    protected function prepareQuery()
    {
        // select h.object_name, hg.object_name,
        // CASE WHEN hgh.host_id IS NULL THEN 'apply' ELSE 'direct' END AS assi
        // from icinga_hostgroup_host_resolved hgr join icinga_host h on h.id = hgr.host_id
        // join icinga_hostgroup hg on hgr.hostgroup_id = hg.id
        // left join icinga_hostgroup_host hgh on hgh.host_id = h.id and hgh.hostgroup_id = hg.id;

        $type = $this->getType();
        $columns = [
            'o.id',
            'o.object_type',
            'o.object_name',
            'membership_type' => "CASE WHEN go.${type}_id IS NULL THEN 'apply' ELSE 'direct' END"
        ];

        if ($this->group === null) {
            $columns = ['group_name' => 'g.object_name'] + $columns;
        }
        if ($type === 'service') {
            $columns[] = 'o.assign_filter';
            $columns[] = 'o.host_id';
            $columns[] = 'o.service_set_id';
        }

        $query = $this->db()->select()->from(
            ['gro' => "icinga_${type}group_${type}_resolved"],
            $columns
        )->join(
            ['o' => "icinga_${type}"],
            "o.id = gro.${type}_id",
            []
        )->join(
            ['g' => "icinga_${type}group"],
            "gro.${type}group_id = g.id",
            []
        )->joinLeft(
            ['go' => "icinga_${type}group_${type}"],
            "go.${type}_id = o.id AND go.${type}group_id = g.id",
            []
        )->order('o.object_name');

        if ($this->group !== null) {
            $query->where('g.id = ?', $this->group->get('id'));
        }

        return $query;
    }
}
