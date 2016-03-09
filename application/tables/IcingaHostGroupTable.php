<?php

namespace Icinga\Module\Director\Tables;

use Icinga\Module\Director\Web\Table\QuickTable;

class IcingaHostGroupTable extends QuickTable
{
    protected $searchColumns = array(
        'hostgroup',
        'display_name'
    );

    public function getColumns()
    {
        return array(
            'id'                    => 'hg.id',
            'hostgroup'             => 'hg.object_name',
            'display_name'          => 'hg.display_name'
        );
    }

    protected function getActionUrl($row)
    {
        return $this->url('director/hostgroup', array('name' => $row->hostgroup));
    }

    public function getTitles()
    {
        $view = $this->view();
        return array(
            'hostgroup'    => $view->translate('Hostgroup'),
            'display_name' => $view->translate('Display Name'),
        );
    }

    public function getBaseQuery()
    {
        $db = $this->connection()->getConnection();
        $query = $db->select()->from(
            array('hg' => 'icinga_hostgroup'),
            array()
        );

        return $query;
    }
}
