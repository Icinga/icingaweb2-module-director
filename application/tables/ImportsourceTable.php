<?php

namespace Icinga\Module\Director\Tables;

use Icinga\Module\Director\Web\Table\QuickTable;
use Icinga\Module\Director\Import\Import;
use Icinga\Module\Director\Objects\ImportSource;
use Exception;

class ImportsourceTable extends QuickTable
{
    protected $revalidate = false;

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
        if (! $this->revalidate) {
            return array();
        }
        try {
            $import = new Import(ImportSource::load($row->id, $this->connection()));
            if ($import->providesChanges()) {
                $row->source_name = sprintf(
                    '%s (%s)',
                    $row->source_name,
                    $this->view()->translate('has changes')
                );
                return 'pending-changes';
            } else {
                return 'in-sync';
            }
        } catch (Exception $e) {
            $row->source_name = $row->source_name . ' (' . $e->getMessage() . ')';
            return 'failing';
        }
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
