<?php

namespace Icinga\Module\Director\Tables;

use Icinga\Module\Director\Web\Table\IcingaObjectTable;

class IcingaUserTable extends IcingaObjectTable
{
    protected $searchColumns = array(
        'user',
        'display_name'
    );

    public function getColumns()
    {
        return array(
            'id'                    => 'u.id',
            'object_type'           => 'u.object_type',
            'user'                  => 'u.object_name',
            'display_name'          => 'u.display_name',
            'email'                 => 'u.email',
            'pager'                 => 'u.pager',
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
        );
    }

    public function getUnfilteredQuery()
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

    public function getBaseQuery()
    {
        return $this->getUnfilteredQuery();
    }
}
