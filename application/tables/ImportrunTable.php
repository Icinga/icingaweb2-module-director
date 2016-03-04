<?php

namespace Icinga\Module\Director\Tables;

use Icinga\Module\Director\Web\Table\QuickTable;

class ImportrunTable extends QuickTable
{
    protected $searchColumns = array(
        'source_name',
    );

    public function getColumns()
    {
        $columns = array(
            'id'          => 'r.id',
            'source_id'   => 's.id',
            'source_name' => 's.source_name',
            'start_time'  => 'r.start_time',
            'rowset'      => 'LOWER(HEX(rs.checksum))',
            'cnt_rows'    => 'COUNT(rsr.row_checksum)',
        );

        if ($this->connection->isPgsql()) {
            $columns['rowset'] = "LOWER(ENCODE(rs.checksum, 'hex'))";
        }

        return $columns;
    }

    protected function getActionUrl($row)
    {
        return $this->url('director/importrun', array('id' => $row->id));
    }

    public function getTitles()
    {
        $view = $this->view();
        return array(
            'source_name' => $view->translate('Source name'),
            'start_time'  => $view->translate('Timestamp'),
            'cnt_rows'    => $view->translate('Imported rows'),
        );
    }

    public function count()
    {
        $db = $this->connection()->getConnection();
        return $db->fetchOne(
            $db->select()->from(
                array('sub' => $this->getBaseQuery()->columns($this->getColumns())),
                'COUNT(*)'
            )
        );
    }

    public function getBaseQuery()
    {
        $db = $this->connection()->getConnection();

        $query = $db->select()->from(
            array('s' => 'import_source'),
            array()
        )->join(
            array('r' => 'import_run'),
            'r.source_id = s.id',
            array()
        )->joinLeft(
            array('rs' => 'imported_rowset'),
            'rs.checksum = r.rowset_checksum',
            array()
        )->joinLeft(
            array('rsr' => 'imported_rowset_row'),
            'rs.checksum = rsr.rowset_checksum',
            array()
        )->group('r.id')->group('s.id')->group('rs.checksum')
         ->order('r.start_time DESC');

        return $query;
    }
}
