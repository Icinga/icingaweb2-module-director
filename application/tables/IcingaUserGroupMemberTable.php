<?php

namespace Icinga\Module\Director\Tables;

use Icinga\Module\Director\Web\Table\QuickTable;

class IcingaUserGroupMemberTable extends QuickTable
{
    public function getColumns()
    {
        return array(
            'usergroup_id'          => 'ug.id',
            'user_id'               => 'u.id',
            'usergroup'             => 'ug.object_name',
            'user'                  => 'u.object_name'
        );
    }

    protected function getActionUrl($row)
    {
        return $this->url('director/object/usergroupmember', array(
            'usergroup_id' => $row->usergroup_id,
            'user_id'      => $row->user_id,
        ));
    }

    public function getTitles()
    {
        $view = $this->view();
        return array(
            'usergroup' => $view->translate('Usergroup'),
            'user'      => $view->translate('Member'),
        );
    }

    public function fetchData()
    {
        $db = $this->connection()->getConnection();
        $query = $db->select()->from(
            array('ug' => 'icinga_usergroup'),
            $this->getColumns()
        )->join(
            array('ugu' => 'icinga_usergroup_user'),
            'ugu.usergroup_id = ug.id',
            array()
        )->join(
            array('u' => 'icinga_user'),
            'ugu.user_id = u.id',
            array()
        );

        return  $db->fetchAll($query);
    }
}
