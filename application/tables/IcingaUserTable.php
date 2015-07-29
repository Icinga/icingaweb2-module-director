<?php

namespace Icinga\Module\Director\Tables;

use Icinga\Module\Director\Web\Table\QuickTable;

class IcingaUserTable extends QuickTable
{
    protected $searchColumns = array(
        'user',
    );

    public function getColumns()
    {
        return array(
            'id'                    => 'u.id',
            'user'                  => 'u.object_name',
            // 'display_name'          => 'u.display_name',
            'email'                 => 'u.email',
            // 'pager'                 => 'u.pager',
            // 'enable_notifications'  => 'u.enable_notifications',
            // 'period'                => ''
            'zone'                  => 'z.object_name',
        );
    }

    protected function getActionUrl($row)
    {
        return $this->url('director/user', array('name' => $row->user));
    }

    public function getTitles()
    {
        $view = $this->view();
        return array(
            'user'          => $view->translate('Username'),
            'email'         => $view->translate('Email'),
            'zone'          => $view->translate('Zone'),
        );
    }

    public function getBaseQuery()
    {
        $db = $this->connection()->getConnection();
        $query = $db->select()->from(
            array('u' => 'icinga_user'),
            array()
        )->joinLeft(
            array('z' => 'icinga_zone'),
            'u.zone_id = z.id',
            array()
        );

        return $query;
    }
}
