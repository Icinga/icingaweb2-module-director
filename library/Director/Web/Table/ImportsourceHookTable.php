<?php

namespace Icinga\Module\Director\Web\Table;

use ipl\Html\ValidHtml;
use Icinga\Data\DataArray\ArrayDatasource;
use Icinga\Module\Director\Hook\ImportSourceHook;
use Icinga\Module\Director\Import\SyncUtils;
use Icinga\Module\Director\Objects\ImportSource;
use Icinga\Module\Director\PlainObjectRenderer;
use ipl\Html\Html;
use gipfl\IcingaWeb2\Table\SimpleQueryBasedTable;

class ImportsourceHookTable extends SimpleQueryBasedTable
{
    /** @var  ImportSource */
    protected $source;

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
        if (\is_array($row)) {
            $row = (object) $row;
        }
        $tr = $this::tr();

        foreach ($this->getColumnsToBeRendered() as $column) {
            $td = $this::td();
            if (\property_exists($row, $column)) {
                if (\is_string($row->$column) || $row->$column instanceof ValidHtml) {
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

    public function prepareQuery()
    {
        $data = $this->sourceHook()->fetchData();
        $this->source->applyModifiers($data);

        $ds = new ArrayDatasource($data);
        return $ds->select();
    }
}
