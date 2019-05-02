<?php

namespace Icinga\Module\Director\Web\Table;

use ipl\Html\Html;
use ipl\Html\ValidHtml;
use gipfl\IcingaWeb2\Table\SimpleQueryBasedTable;
use Icinga\Data\DataArray\ArrayDatasource;
use Icinga\Module\Director\Objects\ImportRun;
use Icinga\Module\Director\PlainObjectRenderer;

class ImportedrowsTable extends SimpleQueryBasedTable
{
    protected $columns;

    /** @var ImportRun */
    protected $importRun;

    public static function load(ImportRun $run)
    {
        $table = new static();
        $table->setImportRun($run);
        return $table;
    }

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

            $keyColumn = $this->importRun->importSource()->get('key_column');
            if ($keyColumn !== null && ($pos = array_search($keyColumn, $cols)) !== false) {
                unset($cols[$pos]);
                array_unshift($cols, $keyColumn);
            }
        } else {
            $cols = $this->columns;
        }

        return array_combine($cols, $cols);
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

    public function getColumnsToBeRendered()
    {
        return $this->getColumns();
    }

    public function prepareQuery()
    {
        $ds = new ArrayDatasource(
            $this->importRun->fetchRows($this->columns)
        );

        return $ds->select()->order('object_name');
    }
}
