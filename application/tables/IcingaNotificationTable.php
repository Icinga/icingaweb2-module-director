<?php

namespace Icinga\Module\Director\Tables;

use Icinga\Module\Director\Web\Table\IcingaObjectTable;

class IcingaNotificationTable extends IcingaObjectTable
{
    protected $searchColumns = array(
        'user',
    );

    public function getColumns()
    {
        return array(
            'id'                    => 'n.id',
            'object_type'           => 'n.object_type',
            'notification'          => 'n.object_name',
        );
    }

    protected function listTableClasses()
    {
        return array_merge(array('assignment-table'), parent::listTableClasses());
    }

    protected function getActionUrl($row)
    {
        return $this->url('director/notification', array('id' => $row->id));
    }

    public function getTitles()
    {
        $view = $this->view();
        return array(
            'notification' => $view->translate('Notification'),
        );
    }

    protected function renderRow($row)
    {
        $v = $this->view();
        $extra = $this->appliedOnes($row->id);
        $htm = "  <tr" . $this->getRowClassesString($row) . ">\n";
        $htm .= '<td>' . $v->qlink($row->notification, $this->getActionUrl($row));
        if (empty($extra)) {
            $htm .= ' ' . $v->qlink(
                'Create apply-rule',
                'director/notification/add',
                array('apply' => $row->notification),
                array('class'    => 'icon-plus')
            );

        } else {
            $htm .= '. Related apply rules: <ul class="apply-rules">';
            foreach ($extra as $id => $notification) {
                $htm .= '<li>'
                    . $v->qlink($notification, 'director/notification', array('id' => $id))
                    . '</li>';
            }
            $htm .= '</ul>';
            $htm .= $v->qlink(
                'Add more',
                'director/notification/add',
                array('apply' => $row->notification),
                array('class' => 'icon-plus')
            );
        }
        $htm .= '</td>';
        return $htm . "  </tr>\n";
    }

    protected function appliedOnes($id)
    {
        if ($this->connection()->isPgsql()) {
            $nameCol = "s.object_name || COALESCE(': ' || ARRAY_TO_STRING(ARRAY_AGG("
                . "a.assign_type || ' where ' || a.filter_string"
                . " ORDER BY a.assign_type, a.filter_string), ', '), '')";
        } else {
            $nameCol = "s.object_name || COALESCE(': ' || GROUP_CONCAT("
                . "a.assign_type || ' where ' || a.filter_string"
                . " ORDER BY a.assign_type, a.filter_string SEPARATOR ', '"
                . "), '')";
        }

        $db = $this->connection()->getConnection();
        $query = $db->select()->from(
            array('s' => 'icinga_notification'),
            array(
                'id'         => 's.id',
                'objectname' => $nameCol,
            )
        )->join(
            array('i' => 'icinga_notification_inheritance'),
            'i.notification_id = s.id',
            array()
        )->where('i.parent_notification_id = ?', $id)
         ->where('s.object_type = ?', 'apply');

        $query->joinLeft(
            array('a' => 'icinga_notification_assignment'),
            'a.notification_id = s.id',
            array()
        )->group('s.id');

        return $db->fetchPairs($query);
    }

    public function getUnfilteredQuery()
    {
        $db = $this->connection()->getConnection();
        $query = $db->select()->from(
            array('n' => 'icinga_notification'),
            array()
        );

        return $query;
    }

    public function getBaseQuery()
    {
        return $this->getUnfilteredQuery();
    }
}
