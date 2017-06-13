<?php

namespace Icinga\Module\Director\Tables;

use Icinga\Module\Director\Web\Table\QuickTable;

class IcingaUserGroupTable extends QuickTable
{
    protected $searchColumns = array(
        'usergroup',
        'display_name'
    );

    public function getColumns()
    {
        return array(
            'id'                    => 'ug.id',
            'usergroup'             => 'ug.object_name',
            'display_name'          => 'ug.display_name',
        );
    }

    protected function getActionUrl($row)
    {
        return $this->url('director/usergroup', array('name' => $row->usergroup));
    }

    public function getTitles()
    {
        $view = $this->view();
        return array(
            'usergroup'    => $view->translate('Usergroup'),
            'display_name' => $view->translate('Display Name'),
        );
    }

    public function getUnfilteredQuery()
    {

        return $this->db()->select()->from(
            array('ug' => 'icinga_usergroup'),
            array()
        )->order('ug.object_name');
    }

    public function getBaseQuery()
    {
        return $this->getUnfilteredQuery();
    }
}
