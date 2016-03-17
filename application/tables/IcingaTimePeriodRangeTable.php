<?php

namespace Icinga\Module\Director\Tables;

use Icinga\Module\Director\Objects\IcingaTimePeriod;
use Icinga\Module\Director\Web\Table\QuickTable;

class IcingaTimePeriodRangeTable extends QuickTable
{
    protected $period;

    protected $searchColumns = array(
        'timeperiod_key',
        'timeperiod_value',
    );

    public function getColumns()
    {
        return array(
            'timeperiod_id'    => 'r.timeperiod_id',
            'timeperiod_key'   => 'r.timeperiod_key',
            'timeperiod_value' => 'r.timeperiod_value',
        );
    }

    public function setTimePeriod(IcingaTimePeriod $period)
    {
        $this->period = $period;
        $this->setConnection($period->getConnection());
        return $this;
    }

    protected function getActionUrl($row)
    {
        return $this->url(
            'director/timeperiod/ranges',
            array(
                'name'       => $this->period->object_name,
                'range'      => $row->timeperiod_key,
                'range_type' => 'include'
            )
        );
    }

    public function getTitles()
    {
        $view = $this->view();
        return array(
            'timeperiod_key'   => $view->translate('Day(s)'),
            'timeperiod_value' => $view->translate('Timeperiods'),
        );
    }

    public function getBaseQuery()
    {
        $db = $this->connection()->getConnection();
        $query = $db->select()->from(
            array('r' => 'icinga_timeperiod_range'),
            array()
        )->where('r.timeperiod_id = ?', $this->period->id);

        return $query;
    }
}
