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
