<?php

namespace Icinga\Module\Director\Tables;

use Icinga\Module\Director\Web\Table\QuickTable;

class IcingaServiceTable extends QuickTable
{
    protected $searchColumns = array(
        'service',
    );

    public function getColumns()
    {
        return array(
            'id'      => 's.id',
            'service' => 's.object_name',
        );
    }

    protected function getActionUrl($row)
    {
        return $this->url('director/service', array('name' => $row->service));
    }

    public function getTitles()
    {
        $view = $this->view();
        return array(
            'service' => $view->translate('Servicename'),
        );
    }

    public function getUnfilteredQuery()
    {
        $db = $this->connection()->getConnection();
        $query = $db->select()->from(
            array('s' => 'icinga_service'),
            array()
        );

        return $query;
    }

    public function getBaseQuery()
    {
        return $this->getUnfilteredQuery()->where('s.object_type = ?', 'object');
    }
}
