<?php

namespace Icinga\Module\Director\Tables;

use Icinga\Module\Director\Web\Table\QuickTable;

class ImportsourceTable extends QuickTable
{
    public function getColumns()
    {
        return array(
            'id'             => 's.id',
            'source_name'    => 's.source_name',
            'provider_class' => 's.provider_class',
        );
    }

    protected function getActionUrl($row)
    {
        return $this->url('director/importsource/edit', array('id' => $row->id));
    }

    public function getTitles()
    {
        $view = $this->view();
        return array(
            'source_name' => $view->translate('Source name'),
        );
    }

    public function fetchData()
    {
        $db = $this->connection()->getConnection();

        $query = $db->select()->from(
            array('s' => 'import_source'),
            $this->getColumns()
        )->order('source_name ASC');

        return $db->fetchAll($query);
    }
}
