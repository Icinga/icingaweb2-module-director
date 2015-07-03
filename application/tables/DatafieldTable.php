<?php

namespace Icinga\Module\Director\Tables;

use Icinga\Module\Director\Web\Table\QuickTable;

class DatafieldTable extends QuickTable
{
    public function getColumns()
    {
        return array(
            'id'        => 'f.id',
            'varname'   => 'f.varname',
            'datatype'  => 'f.datatype',
        );
    }

    protected function getActionUrl($row)
    {
        return $this->url('director/datafield', array('id' => $row->id));
    }

    public function getTitles()
    {
        $view = $this->view();
        return array(
            'varname'   => $view->translate('Field name'),
            'datatype'  => $view->translate('Data type'),
        );
    }

    public function fetchData()
    {
        $db = $this->connection()->getConnection();

        $query = $db->select()->from(
            array('f' => 'director_datafield'),
            $this->getColumns()
        )->order('varname ASC');

        return $db->fetchAll($query);
    }
}
