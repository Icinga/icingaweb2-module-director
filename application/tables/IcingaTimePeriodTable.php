<?php

namespace Icinga\Module\Director\Tables;

use Icinga\Module\Director\Web\Table\QuickTable;

class IcingaTimePeriodTable extends QuickTable
{
    public function getColumns()
    {
        return array(
            'id'            => 't.id',
            'timeperiod'    => 't.object_name',
            'display_name'  => 't.display_name',
            'zone'          => 'z.object_name',
        );
    }

    protected function getActionUrl($row)
    {
        return $this->url('director/timeperiod', array('id' => $row->id));
    }

    public function getTitles()
    {
        $view = $this->view();
        return array(
            'timeperiod' => $view->translate('Timeperiod'),
            'display_name'  => $view->translate('Display Name'),
            'zone'     => $view->translate('Zone'),
        );
    }

    public function fetchData()
    {
        $db = $this->connection()->getConnection();
        $query = $db->select()->from(
            array('t' => 'icinga_timeperiod'),
            $this->getColumns()
        )->joinLeft(
            array('z' => 'icinga_zone'),
            't.zone_id = z.id',
            array()
        );

        return $db->fetchAll($query);
    }
}
