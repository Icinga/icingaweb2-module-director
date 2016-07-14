<?php

namespace Icinga\Module\Director\Tables;

use Icinga\Data\DataArray\ArrayDatasource;
use Icinga\Module\Director\Objects\ImportRun;
use Icinga\Module\Director\Web\Table\QuickTable;

class ImportedrowsTable extends QuickTable
{
    protected $columns;

    protected $importRun;

    public function setImportRun(ImportRun $run)
    {
        $this->importRun = $run;
        return $this;
    }

    public function setColumns($columns)
    {
        $this->columns = $columns;
        return $this;
    }

    public function getColumns()
    {
        if ($this->columns === null) {
            $cols = $this->importRun->listColumnNames();
        } else {
            $cols = $this->columns;
        }

        return array_combine($cols, $cols);
    }

    public function getTitles()
    {
        $cols = $this->getColumns();
        // TODO: replace key column with object name!?
        //       $view = $this->view();
        //       'object_name' => $view->translate('Object name')
        return array_combine($cols, $cols);
    }

    public function fetchData()
    {
        $query = $this->getBaseQuery()->columns($this->getColumns());

        if ($this->hasLimit() || $this->hasOffset()) {
            $query->limit($this->getLimit(), $this->getOffset());
        }

        return $query->fetchAll();
    }

    public function count()
    {
        return $this->getBaseQuery()->count();
    }

    public function getBaseQuery()
    {
        $ds = new ArrayDatasource(
            $this->importRun->fetchRows($this->columns, $this->filter)
        );

        return $ds->select()->order('object_name');
    }
}
