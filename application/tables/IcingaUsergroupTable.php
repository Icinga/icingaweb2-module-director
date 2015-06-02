<?php

namespace Icinga\Module\Director\Tables;

use Icinga\Module\Director\Web\Table\QuickTable;

class IcingaUsergroupTable extends QuickTable
{
    public function getColumns()
    {
        return array(
            'id'                    => 'ug.id',
            'usergroup'             => 'ug.object_name',
            'display_name'          => 'ug.display_name',
            'zone'                  => 'z.object_name',
        );
    }

    protected function getActionUrl($row)
    {
        return $this->url('director/object/usergroup', array('id' => $row->id));
    }

    public function getTitles()
    {
        $view = $this->view();
        return array(
            'usergroup'         => $view->translate('Usergroup'),
            'display_name'      => $view->translate('Display Name'),
            'zone'              => $view->translate('Zone'),
        );
    }

    public function fetchData()
    {
        $db = $this->connection()->getConnection();
        $query = $db->select()->from(
            array('ug' => 'icinga_usergroup'),
            $this->getColumns()
        )->joinLeft(
            array('z' => 'icinga_zone'),
            'ug.zone_id = z.id',
            array()
        );

        return $db->fetchAll($query);
    }
}
