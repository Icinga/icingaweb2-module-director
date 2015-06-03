<?php

namespace Icinga\Module\Director\Tables;

use Icinga\Module\Director\Web\Table\QuickTable;

class IcingaHostVarTable extends QuickTable
{
    public function getColumns()
    {
        return array(
            'host_id'   => 'hv.host_id',
            'host'      => 'h.object_name',
            'varname'   => 'hv.varname',
            'varvalue'  => 'hv.varvalue',
            'format'    => 'hv.format',
        );
    }

    protected function getActionUrl($row)
    {
        return $this->url('director/object/hostvar', array(
            'host_id' => $row->host_id,
            'varname' => $row->varname,
        ));
    }

    public function getTitles()
    {
        $view = $this->view();
        return array(
            'host'      => $view->translate('Host'),
            'varname'   => $view->translate('Name'),
            'varvalue'  => $view->translate('Value'),
        );
    }

    public function fetchData()
    {
        $db = $this->connection()->getConnection();
        $query = $db->select()->from(
            array('hv' => 'icinga_host_var'),
            $this->getColumns()
        )->join(
            array('h' => 'icinga_host'),
            'hv.host_id = h.id',
            array()
        );

        return $db->fetchAll($query);
    }
}
