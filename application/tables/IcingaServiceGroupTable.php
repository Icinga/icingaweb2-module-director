<?php

namespace Icinga\Module\Director\Tables;

use Icinga\Module\Director\Web\Table\QuickTable;

class IcingaServiceGroupTable extends QuickTable
{
    public function getColumns()
    {
        return array(
            'id'                    => 'sg.id',
            'servicegroup'          => 'sg.object_name',
            'display_name'          => 'sg.display_name'
        );
    }

    protected function getActionUrl($row)
    {
        return $this->url('director/servicegroup', array('name' => $row->servicegroup));
    }

    public function getTitles()
    {
        $view = $this->view();
        return array(
            'servicegroup' => $view->translate('Servicegroup'),
            'display_name' => $view->translate('Display Name'),
        );
    }

    public function fetchData()
    {
        $db = $this->connection()->getConnection();
        $query = $db->select()->from(
            array('sg' => 'icinga_servicegroup'),
            $this->getColumns()
        );

        return $db->fetchAll($query);
    }
}
