<?php

namespace Icinga\Module\Director\Tables;

use Icinga\Module\Director\Web\Table\QuickTable;

class IcingaHostgroupTable extends QuickTable
{
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
        return $this->url('director/object/hostgroup', array('id' => $row->id));
    }

    public function getTitles()
    {
        $view = $this->view();
        return array(
            'hostgroup'         => $view->translate('Hostgroup'),
            'display_name'      => $view->translate('Display Name'),
        );
    }

    public function fetchData()
    {
        $db = $this->connection()->getConnection();
        $query = $db->select()->from(
            array('hg' => 'icinga_hostgroup'),
            $this->getColumns()
        );

        return $db->fetchAll($query);
    }
}
