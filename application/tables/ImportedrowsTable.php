<?php

namespace Icinga\Module\Director\Tables;

use Icinga\Module\Director\Web\Table\QuickTable;
use Icinga\Data\DataArray\ArrayDatasource;

class ImportedrowsTable extends QuickTable
{
    protected $checksum;

    public function getColumns()
    {
        $db = $this->connection();
        $cols = $db->listImportedRowsetColumnNames($this->checksum);
        return array_combine($cols, $cols);
    }

    public function setChecksum($checksum)
    {
        $this->checksum = $checksum;
        return $this;
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

        // TODO: move to dedicated method in parent class
        $filter = null;
        $enforced = $this->enforcedFilters;
        if ($this->filter && ! $this->filter->isEmpty()) {
            $filter = $this->filter;
        } elseif (! empty($enforced)) {
            $filter = array_shift($enforced);
        }
        if ($filter) {
            foreach ($enforced as $f) {
                $filter->andFilter($f);
            }
            $query->where($this->renderFilter($filter));
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
            $this->connection()->fetchImportedRowsetRows(
                $this->checksum,
                null
            )
        );

        return $ds->select()->order('object_name');
        // TODO: Remove? ->
        return $this->connection()->createImportedRowsetRowsQuery(
            $this->checksum
        )->order('object_name');
    }
}
