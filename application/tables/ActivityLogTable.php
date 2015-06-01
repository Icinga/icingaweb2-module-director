<?php

namespace Icinga\Module\Director\Tables;

use Icinga\Module\Director\Web\Table\QuickTable;

class ActivityLogTable extends QuickTable
{
    public function getColumns()
    {
        return array(
            'id'              => 'l.id',
            'change_time'     => 'l.change_time',
            'author'          => 'l.author',
            'action'          => "CONCAT(l.action_name || ' ' || REPLACE(l.object_type, 'icinga_', '') || ' \"' || l.object_name || '\"')"
        );
    }

    protected function getActionLinks($id)
    {
        return $this->view()->qlink('Show', 'director/show/activitylog', array('id' => $id));
    }

    public function getTitles()
    {
        $view = $this->view();
        return array(
            'change_time'    => $view->translate('Timestamp'),
            'author'         => $view->translate('Author'),
            'action'    => $view->translate('Action'),
        );
    }

    public function fetchData()
    {
        $db = $this->connection()->getConnection();

        $query = $db->select()->from(
            array('l' => 'director_activity_log'),
            $this->getColumns()
        )->order('change_time DESC');

        return $db->fetchAll($query);
    }
}
