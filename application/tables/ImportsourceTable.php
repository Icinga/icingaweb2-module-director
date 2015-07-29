<?php

namespace Icinga\Module\Director\Tables;

use Icinga\Module\Director\Web\Table\QuickTable;

class ImportsourceTable extends QuickTable
{
    protected $searchColumns = array(
        'source_name',
    );

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

    protected function renderAdditionalActions($row)
    {
        return $this->view->qlink(
            'Preview',
            'director/importsource/preview',
            array('id' => $row->id)
        ) . ' ' . $this->view->qlink(
            'Run',
            'director/importsource/run',
            array('id' => $row->id),
            array('data-base-target' => '_main')
        );
    }

    public function getTitles()
    {
        $view = $this->view();
        return array(
            'source_name' => $view->translate('Source name'),
        );
    }

    public function getBaseQuery()
    {
        $db = $this->connection()->getConnection();

        $query = $db->select()->from(
            array('s' => 'import_source'),
            array()
        )->order('source_name ASC');

        return $query;
    }
}
