<?php

namespace Icinga\Module\Director\Tables;

use Icinga\Module\Director\Web\Table\QuickTable;

class IcingaServiceVarTable extends QuickTable
{
    public function getColumns()
    {
        return array(
            'service_id'   => 'sv.service_id',
            'service'      => 'h.object_name',
            'varname'      => 'sv.varname',
            'varvalue'     => 'sv.varvalue',
            'format'       => 'sv.format',
        );
    }

    protected function getActionUrl($row)
    {
        return $this->url('director/object/servicevar', array(
            'service_id' => $row->service_id,
            'varname' => $row->varname,
        ));
    }

    public function getTitles()
    {
        $view = $this->view();
        return array(
            'service'      => $view->translate('Service'),
            'varname'   => $view->translate('Name'),
            'varvalue'  => $view->translate('Value'),
        );
    }

    public function getBaseQuery()
    {
        $db = $this->connection()->getConnection();
        $query = $db->select()->from(
            array('sv' => 'icinga_service_var'),
            array()
        )->join(
            array('h' => 'icinga_service'),
            'sv.service_id = h.id',
            array()
        );

        return $query;
    }
}
