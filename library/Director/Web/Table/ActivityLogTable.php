<?php

namespace Icinga\Module\Director\Web\Table;

use Icinga\Module\Director\Util;
use ipl\Html\BaseElement;
use ipl\Html\Link;
use ipl\Web\Table\ZfQueryBasedTable;

class ActivityLogTable extends ZfQueryBasedTable
{
    protected $filters = [];

    protected $lastDeployedId;

    protected $extraParams = [];

    protected $columnCount;

    /** @var BaseElement */
    protected $currentHead;

    /** @var BaseElement */
    protected $currentBody;

    protected $searchColumns = array(
        'l.author',
        'l.object_name',
        'l.object_type',
        'l.action_name',
    );

    public function assemble()
    {
        $this->attributes()->add('class', 'activity-log');
    }

    public function setLastDeployedId($id)
    {
        $this->lastDeployedId = $id;
        return $this;
    }

    public function renderRow($row)
    {
        $this->splitByDay($row->ts_change_time);
        $action = 'action-' . $row->action. ' ';
        if ($row->id > $this->lastDeployedId) {
            $action .= 'undeployed';
        } else {
            $action .= 'deployed';
        }

        return $this::tr([
            $this::td($this->makeLink($row))->setSeparator(' '),
            $this::td(strftime('%H:%M:%S', $row->ts_change_time))
        ])->addAttributes(['class' => $action]);
    }

    protected function makeLink($row)
    {
        if (Util::hasPermission('director/showconfig')) {
            // Later on replacing, service_set -> serviceset
            $type = $row->object_type;
            $name = $row->object_name;
            if (substr($type, 0, 7) === 'icinga_') {
                $type = substr($type, 7);
            }

            // multi column key :(
            if ($type === 'service') {
                $object = "\"$name\"";
            } else {
                $object = Link::create(
                    "\"$name\"",
                    'director/' . str_replace('_', '', $type),
                    ['name' => $name],
                    ['title' => $this->translate('Jump to this object')]
                );
            }

            return [
                '[' . $row->author . ']',
                Link::create(
                    $row->action,
                    'director/show/activitylog',
                    array_merge(['id' => $row->id], $this->extraParams),
                    ['title' => $this->translate('Show details related to this change')]
                ),
                str_replace('_', ' ', $type),
                $object
            ];
        } else {
            return $row->log_message;
        }
    }

    public function filterObject($type, $name)
    {
        $this->filters[] = ['l.object_type = ?', $type];
        $this->filters[] = ['l.object_name = ?', $name];
        $this->extraParams = [
            'type' => $type,
            'name' => $name,
        ];

        return $this;
    }

    public function getColumns()
    {
        return [
            'log_message'     => "'[' || l.author || '] ' || l.action_name || ' '"
                . " || REPLACE(l.object_type, 'icinga_', '')"
                . " || ' \"' || l.object_name || '\"'",
            'author'          => 'l.author',
            'action'          => 'l.action_name',
            'object_name'     => 'l.object_name',
            'object_type'     => 'l.object_type',
            'id'              => 'l.id',
            'change_time'     => 'l.change_time',
            'ts_change_time'  => 'UNIX_TIMESTAMP(l.change_time)',
        ];
    }

    public function prepareQuery()
    {
        $query = $this->db()->select()->from(
            ['l' => 'director_activity_log'],
            $this->getColumns()
        )->order('change_time DESC')->order('id DESC')->limit(100);

        foreach ($this->filters as $filter) {
            $query->where($filter[0], $filter[1]);
        }

        return $query;
    }
}
