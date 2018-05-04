<?php

namespace Icinga\Module\Director\Web\Table;

use dipl\Html\ValidHtml;
use Icinga\Data\DataArray\ArrayDatasource;
use Icinga\Module\Director\Hook\ImportSourceHook;
use Icinga\Module\Director\Import\SyncUtils;
use Icinga\Module\Director\Objects\ImportSource;
use Icinga\Module\Director\PlainObjectRenderer;
use dipl\Html\Html;
use dipl\Web\Table\SimpleQueryBasedTable;

class ImportsourceHookTable extends SimpleQueryBasedTable
{
    /** @var  ImportSource */
    protected $source;

    protected $dataCache;

    protected $columnCache;

    protected $sourceHook;

    protected function assemble()
    {
        $this->getAttributes()->add('class', 'raw-data-table collapsed');
    }

    public function getColumns()
    {
        if ($this->columnCache === null) {
            $this->columnCache = SyncUtils::getRootVariables(array_merge(
                $this->sourceHook()->listColumns(),
                $this->source->listModifierTargetProperties()
            ));

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

    public function getColumnsToBeRendered()
    {
        return $this->getColumns();
    }

    public function renderRow($row)
    {
        // Find a better place!
        if ($row === null) {
            return null;
        }
        $tr = $this::tr();

        foreach ($this->getColumnsToBeRendered() as $column) {
            $td = $this::td();
            if (property_exists($row, $column)) {
                if (is_string($row->$column) || $row->$column instanceof ValidHtml) {
                    $td->setContent($row->$column);
                } else {
                    $html = Html::tag('pre', null, PlainObjectRenderer::render($row->$column));
                    $td->setContent($html);
                }
            }
            $tr->add($td);
        }

        return $tr;
    }

    protected function sourceHook()
    {
        if ($this->sourceHook === null) {
            $this->sourceHook = ImportSourceHook::forImportSource(
                $this->source
            );
        }

        return $this->sourceHook;
    }

    public function fetchQueryRows()
    {
        if ($this->dataCache === null) {
            $this->dataCache = parent::fetchQueryRows();
            $this->source->applyModifiers($this->dataCache);
        }

        return $this->dataCache;
    }

    public function prepareQuery()
    {
        $ds = new ArrayDatasource($this->sourceHook()->fetchData());
        return $ds->select();
    }
}
