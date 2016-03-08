<?php

namespace Icinga\Module\Director\Tables;

use Icinga\Data\DataArray\ArrayDatasource;
use Icinga\Data\Paginatable;
use Icinga\Module\Director\Hook\ImportSourceHook;
use Icinga\Module\Director\Objects\ImportSource;
use Icinga\Module\Director\Web\Table\QuickTable;

class ImportsourceHookTable extends QuickTable
{
    protected $source;

    protected $dataCache;

    protected $columnCache;

    protected $sourceHook;

    public function getColumns()
    {
        if ($this->columnCache === null) {
            $this->columnCache = $this->sourceHook()->listColumns();
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
            $this->applyModifiers();
        }

        return $this->dataCache;
    }

    protected function applyModifiers()
    {
        $modifiers = $this->source->fetchRowModifiers();
        if (empty($modifiers)) {
            return;
        }

        $propertyModifiers = array();


        foreach ($modifiers as $mod) {
            if (! array_key_exists($mod->property_name, $propertyModifiers)) {
                $propertyModifiers[$mod->property_name] = array();
            }
            $obj = new $mod->provider_class;
            $obj->setSettings($mod->getSettings());
            $obj->setDb($this->source->getConnection());
            $propertyModifiers[$mod->property_name][] = $obj;
            
        }

        foreach ($this->dataCache as & $row) {
            foreach ($propertyModifiers as $key => $mods) {
                foreach ($mods as $mod) {
                    if (is_array($row->$key)) {
                        foreach ($row->$key as & $k) {
                            $k = $mod->transform($k);
                        }
                    } else {
                        $row->$key = $mod->transform($row->$key);
                    }
                }
            }
        }
    }

    public function getBaseQuery()
    {
        $ds = new ArrayDatasource($this->sourceHook()->fetchData());
        return $ds->select();
    }
}
