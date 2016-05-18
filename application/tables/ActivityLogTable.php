<?php

namespace Icinga\Module\Director\Tables;

use Icinga\Module\Director\Web\Table\QuickTable;

class ActivityLogTable extends QuickTable
{
    protected $filters = array();

    protected $lastDeployedId;

    protected $extraParams = array();

    protected $lastDay;

    protected $columnCount;

    protected $isUsEnglish;

    public function getColumns()
    {
        return array(
            'id'              => 'l.id',
            'change_time'     => 'l.change_time',
            'ts_change_time'  => 'UNIX_TIMESTAMP(l.change_time)',
            'author'          => 'l.author',
            'action'          => 'l.action_name',
            'log_message'     => "'[' || l.author || '] ' || l.action_name || ' ' || REPLACE(l.object_type, 'icinga_', '')"
                               . " || ' \"' || l.object_name || '\"'",
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

    protected function renderRow($row)
    {
        $row->change_time = strftime('%H:%M:%S', $row->ts_change_time);
        return $this->renderDayIfNew($row) . parent::renderRow($row);
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
            // 'author'      => $view->translate('Author'),
            'log_message' => $view->translate('Action'),
            'change_time' => $view->translate('Timestamp'),
        );
    }

    protected function renderTitles($row)
    {
        return '';
    }

    protected function isUsEnglish()
    {
        if ($this->isUsEnglish === null) {
            $this->isUsEnglish = in_array(setlocale(LC_ALL, 0), array('en_US.UTF-8', 'C'));
        }

        return $this->isUsEnglish;
    }

    protected function renderDayIfNew($row)
    {
        $view = $this->view();

        if ($this->isUsEnglish()) {
            $day = date('l, jS F Y', (int) $row->ts_change_time);
        } else {
            $day = strftime('%A, %e. %B, %Y', (int) $row->ts_change_time);
        }

        if ($this->lastDay === $day) {
            return;
        }

        if ($this->lastDay === null) {
            $htm = "<thead>\n  <tr>\n";
        } else {
            $htm = "</tbody>\n<thead>\n  <tr>\n";
        }

        if ($this->columnCount === null) {
            $this->columnCount = count($this->getTitles());
        }

        $htm .= '<th colspan="' . $this->columnCount . '">' . $this->view()->escape($day) . '</th>' . "\n";
        if ($this->lastDay === null) {
            $htm .= "  </tr>\n";
        } else {
            $htm .= "  </tr>\n</thead>\n";
        }

        $this->lastDay = $day;

        return $htm . "<tbody>\n";
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
