<?php

namespace Icinga\Module\Director\Tables;

use Icinga\Data\DataArray\ArrayDatasource;
use Icinga\Data\Paginatable;
use Icinga\Module\Director\Hook\ImportSourceHook;
use Icinga\Module\Director\Objects\ImportSource;
use Icinga\Module\Director\Web\Table\QuickTable;

class ImportsourceHookTable extends QuickTable
{
    /** @var  ImportSource */
    protected $source;

    protected $dataCache;

    protected $columnCache;

    protected $sourceHook;

    public function getColumns()
    {
        if ($this->columnCache === null) {
            $this->columnCache = array_merge(
                $this->sourceHook()->listColumns(),
                $this->source->listModifierTargetProperties()
            );

            sort($this->columnCache);

            // prioritize key column
            $keyColumn = $this->source->get('key_column');
            if ($keyColumn !== null && ($pos = array_search($keyColumn, $this->columnCache)) !== false) {
                unset($this->columnCache[$pos]);
                array_unshift($this->columnCache, $keyColumn);
            }
        }

        return $this->columnCache;
    }

    public function setImportSource(ImportSource $source)
    {
        $this->source = $source;
        return $this;
    }

    public function getTitles()
    {
        $cols = $this->getColumns();
        return array_combine($cols, $cols);
    }

    public function count()
    {
        $q = clone($this->getBaseQuery());
        return $q->count();
    }

    protected function sourceHook()
    {
        if ($this->sourceHook === null) {
            $this->sourceHook = ImportSourceHook::loadByName(
                $this->source->source_name,
                $this->connection()
            );
        }

        return $this->sourceHook;
    }

    public function fetchData()
    {
        if ($this->dataCache === null) {

            $query = $this->getBaseQuery()->columns($this->getColumns());

            if ($this->hasLimit() || $this->hasOffset()) {
                $query->limit($this->getLimit(), $this->getOffset());
            }

            $this->dataCache = $query->fetchAll();
            $this->source->applyModifiers($this->dataCache);
        }

        return $this->dataCache;
    }

    public function getBaseQuery()
    {
        $ds = new ArrayDatasource($this->sourceHook()->fetchData());
        return $ds->select();
    }
}
