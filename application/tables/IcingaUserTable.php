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
            'disabled'              => 'u.disabled'
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

    protected function getRowClasses($row)
    {
        if ($row->disabled === 'y') {
            return 'disabled';
        } else {
            return null;
        }
    }


    public function getUnfilteredQuery()
    {
        return $this->db()->select()->from(
            array('u' => 'icinga_user'),
            array()
        )->joinLeft(
            array('z' => 'icinga_zone'),
            'u.zone_id = z.id',
            array()
        )->order('u.object_name');
    }

    public function getBaseQuery()
    {
        return $this->getUnfilteredQuery();
    }
}
