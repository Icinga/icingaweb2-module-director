<?php

namespace Icinga\Module\Director\Web\Table;

use Icinga\Module\Director\Db;
use Icinga\Module\Director\Objects\IcingaObjectGroup;
use ipl\Html\Link;
use ipl\Web\Component\ControlsAndContent;
use ipl\Web\Table\ZfQueryBasedTable;
use ipl\Web\Url;

class GroupMemberTable extends ZfQueryBasedTable
{
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

    public function setGroup(IcingaObjectGroup $group)
    {
        $this->group = $group;
        return $this;
    }

    public function renderTo(ControlsAndContent $controller)
    {
        $url = $controller->url();
        $this->initializeOptionalQuickSearch($controller);
        $controller->content()->add([
            $this->getPaginator($url),
            $this
        ]);

        if ($url->getParam('format') === 'sql') {
            $controller->content()->prepend($this->dumpSqlQuery($url));
        }
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
                $this->translate('Type')
            ];
        } else {
            return [
                $this->translate('Member'),
                $this->translate('Type')
            ];
        }
    }

    public function renderRow($row)
    {
        $type = $this->getType();
        $url = Url::fromPath("director/${type}", [
            'name' => $row->object_name
        ]);

        $tr = $this::tr();

        if ($this->group === null) {
            $tr->add($this::td($row->group_name));

        }

        $tr->add([
            $this::td(Link::create($row->object_name, $url)),
            $this::td($row->membership_type)
        ]);
        return $tr;
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
            'o.object_name',
            'membership_type' => "CASE WHEN go.${type}_id IS NULL THEN 'apply' ELSE 'direct' END"
        ];

        if ($this->group === null) {
            $columns = ['group_name' => 'g.object_name'] + $columns;
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
