<?php

namespace Icinga\Module\Director\Tables;

use Icinga\Module\Director\Web\Table\QuickTable;

class ActivityLogTable extends QuickTable
{
    protected $filters = array();

    protected $lastDeployedId;

    protected $extraParams = array();

    public function getColumns()
    {
        return array(
            'id'              => 'l.id',
            'change_time'     => 'l.change_time',
            'author'          => 'l.author',
            'action'          => "CONCAT(l.action_name || ' ' || REPLACE(l.object_type, 'icinga_', '')"
                               . " || ' \"' || l.object_name || '\"')",
            'action_name'     => 'l.action_name',
        );
    }

    public function setLastDeployedId($id)
    {
        $this->lastDeployedId = $id;
        return $this;
    }

    protected function listTableClasses()
    {
        return array_merge(array('activity-log'), parent::listTableClasses());
    }

    protected function getRowClasses($row)
    {
        $action = 'action-' . $row->action_name . ' ';

        if ($row->id > $this->lastDeployedId) {
            return $action . 'undeployed';
        } else {
            return $action . 'deployed';
        }
    }

    protected function getActionUrl($row)
    {
        return $this->url(
            'director/show/activitylog',
            array_merge(array('id' => $row->id), $this->extraParams)
        );
    }

    public function getTitles()
    {
        $view = $this->view();
        return array(
            'change_time' => $view->translate('Timestamp'),
            'author'      => $view->translate('Author'),
            'action'      => $view->translate('Action'),
        );
    }

    public function filterObject($type, $name)
    {
        $this->filters[] = array('l.object_type = ?', $type);
        $this->filters[] = array('l.object_name = ?', $name);
        $this->extraParams = array(
            'type' => $type,
            'name' => $name,
        );

        return $this;
    }

    public function getBaseQuery()
    {
        $db = $this->connection()->getConnection();

        $query = $db->select()->from(
            array('l' => 'director_activity_log'),
            array()
        )->order('change_time DESC')->order('id DESC');

        foreach ($this->filters as $filter) {
            $query->where($filter[0], $filter[1]);
        }

        return $query;
    }
}
