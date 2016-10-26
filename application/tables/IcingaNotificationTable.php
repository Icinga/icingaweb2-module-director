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
                array('apply' => $row->notification, 'type' => 'apply'),
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
        $db = $this->connection()->getConnection();
        $query = $db->select()->from(
            array('s' => 'icinga_notification'),
            array(
                'id'         => 's.id',
                'objectname' => 's.object_name',
            )
        )->join(
            array('i' => 'icinga_notification_inheritance'),
            'i.notification_id = s.id',
            array()
        )->where('i.parent_notification_id = ?', $id)
         ->where('s.object_type = ?', 'apply');


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
