<?php

namespace Icinga\Module\Director\Tables;

use Icinga\Module\Director\Web\Table\QuickTable;
use Icinga\Module\Director\Import\Import;
use Icinga\Module\Director\Objects\ImportSource;
use Exception;

class ImportsourceTable extends QuickTable
{
    protected $searchColumns = array(
        'source_name',
    );

    public function getColumns()
    {
        return array(
            'id'                 => 's.id',
            'source_name'        => 's.source_name',
            'provider_class'     => 's.provider_class',
            'import_state'       => 's.import_state',
            'last_error_message' => 's.last_error_message',
        );
    }

    protected function getActionUrl($row)
    {
        return $this->url('director/importsource', array('id' => $row->id));
    }

    public function getTitles()
    {
        $view = $this->view();
        return array(
            'source_name' => $view->translate('Source name'),
        );
    }

    protected function listTableClasses()
    {
        return array_merge(array('syncstate'), parent::listTableClasses());
    }

    protected function getRowClasses($row)
    {
        if ($row->import_state === 'failing' && $row->last_error_message) {
            $row->source_name .= ' (' . $row->last_error_message . ')';
        }

        return $row->import_state;
    }

    public function getBaseQuery()
    {
        return $this->db()->select()->from(
            array('s' => 'import_source'),
            array()
        )->order('source_name ASC');
    }
}
