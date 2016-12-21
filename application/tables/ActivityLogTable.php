<?php

namespace Icinga\Module\Director\Tables;

use Icinga\Module\Director\Util;
use Icinga\Module\Director\Web\Table\QuickTable;

class ActivityLogTable extends QuickTable
{
    protected $filters = array();

    protected $lastDeployedId;

    protected $extraParams = array();

    protected $lastDay;

    protected $columnCount;

    protected $isUsEnglish;

    protected $searchColumns = array(
        //'log_message'
        'author',
        'object_name',
        'object_type',
        'action',
    );

    public function getColumns()
    {
        return array(
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
        );
    }

    public function setLastDeployedId($id)
    {
        $this->lastDeployedId = $id;
        return $this;
    }

    protected function listTableClasses()
    {
        if (Util::hasPermission('director/showconfig')) {
            return array_merge(array('activity-log'), parent::listTableClasses());
        } else {
            return array('simple', 'common-table', 'activity-log');
        }
    }

    public function render()
    {
        $data = $this->fetchData();

        $htm = '<table' . $this->createClassAttribute($this->listTableClasses()) . '>' . "\n"
             . $this->renderTitles($this->getTitles());
        foreach ($data as $row) {
            $htm .= $this->renderRow($row);
        }
        return $htm . "</tbody>\n</table>\n";
    }

    protected function renderRow($row)
    {
        $row->change_time = strftime('%H:%M:%S', $row->ts_change_time);
        return $this->renderDayIfNew($row) . parent::renderRow($row);
    }

    protected function getRowClasses($row)
    {
        $action = 'action-' . $row->action. ' ';

        if ($row->id > $this->lastDeployedId) {
            return $action . 'undeployed';
        } else {
            return $action . 'deployed';
        }
    }

    protected function getActionUrl($row)
    {
        if (Util::hasPermission('director/showconfig')) {
            return $this->url(
                'director/show/activitylog',
                array_merge(array('id' => $row->id), $this->extraParams)
            );

        } else {
            return false;
        }
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

    /**
     * @param object $row
     * @return string
     */
    protected function renderDayIfNew($row)
    {
        $view = $this->view();

        if ($this->isUsEnglish()) {
            $day = date('l, jS F Y', (int) $row->ts_change_time);
        } else {
            $day = strftime('%A, %e. %B, %Y', (int) $row->ts_change_time);
        }

        if ($this->lastDay === $day) {
            return '';
        }

        if ($this->lastDay === null) {
            $htm = "<thead>\n  <tr>\n";
        } else {
            $htm = "</tbody>\n<thead>\n  <tr>\n";
        }

        if ($this->columnCount === null) {
            $this->columnCount = count($this->getTitles());
        }

        $htm .= '<th colspan="' . $this->columnCount . '">' . $view->escape($day) . '</th>' . "\n";
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
        $query = $this->db()->select()->from(
            array('l' => 'director_activity_log'),
            array()
        )->order('change_time DESC')->order('id DESC');

        foreach ($this->filters as $filter) {
            $query->where($filter[0], $filter[1]);
        }

        return $query;
    }
}
