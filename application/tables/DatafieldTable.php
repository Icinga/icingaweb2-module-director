<?php

namespace Icinga\Module\Director\Tables;

use Icinga\Module\Director\Web\Table\QuickTable;

class DatafieldTable extends QuickTable
{
    protected $searchColumns = array(
        'varname',
    );

    public function getColumns()
    {
        return array(
            'id'          => 'f.id',
            'varname'     => 'f.varname',
            'caption'     => 'f.caption',
            'description' => 'f.description',
            'datatype'    => 'f.datatype',
        );
    }

    protected function getActionUrl($row)
    {
        return $this->url('director/datafield/edit', array('id' => $row->id));
    }

    public function getTitles()
    {
        $view = $this->view();
        return array(
            'caption'     => $view->translate('Label'),
            'varname'     => $view->translate('Field name'),
        );
    }

    public function getBaseQuery()
    {
        return $this->db()->select()->from(
            array('f' => 'director_datafield'),
            array()
        )->order('caption ASC');
    }
}
