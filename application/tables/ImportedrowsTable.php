<?php

namespace Icinga\Module\Director\Tables;

use Icinga\Module\Director\Web\Table\QuickTable;

class ImportedrowsTable extends QuickTable
{
    protected $checksum;

    public function getColumns()
    {
        $db = $this->connection();
        return $db->listImportedRowsetColumnNames($this->checksum);
    }

    public function setChecksum($checksum)
    {
        $this->checksum = $checksum;
        return $this;
    }

    public function getTitles()
    {
        $view = $this->view();
        $cols = $this->getColumns();
        return array(
            'object_name' => $view->translate('Object name'),
        ) + array_combine($cols, $cols);
    }

    public function count()
    {
        $db = $this->connection()->getConnection();
        $query = $db->select()
            ->from('imported_rowset_row', 'COUNT(*)')
            ->where('rowset_checksum = ?', $this->checksum);

        return $db->fetchOne($query);
    }

    public function fetchData()
    {
        $db = $this->connection()->getConnection();
        $query = $this->getBaseQuery();

        if ($this->hasLimit() || $this->hasOffset()) {
            $query->limit($this->getLimit(), $this->getOffset());
        }

        return $db->fetchAll($query);
    }

    public function getBaseQuery()
    {
        return $this->connection()->createImportedRowsetRowsQuery(
            $this->checksum
        )->order('object_name');
    }
}
