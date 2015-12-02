<?php

namespace Icinga\Module\Director\Tables;

use Icinga\Module\Director\Web\Table\QuickTable;

class IcingaTimePeriodTable extends QuickTable
{
    protected $searchColumns = array(
        'timeperiod',
    );

    public function getColumns()
    {
        return array(
            'id'            => 't.id',
            'timeperiod'    => 't.object_name',
            'display_name'  => 't.display_name',
        );
    }

    protected function getActionUrl($row)
    {
        return $this->url('director/timeperiod', array('name' => $row->timeperiod));
    }

    public function getTitles()
    {
        $view = $this->view();
        return array(
            'timeperiod' => $view->translate('Timeperiod'),
            'display_name'  => $view->translate('Display Name'),
        );
    }

    public function getBaseQuery()
    {
        $db = $this->connection()->getConnection();
        $query = $db->select()->from(
            array('t' => 'icinga_timeperiod'),
            array()
        );

        return $query;
    }
}
