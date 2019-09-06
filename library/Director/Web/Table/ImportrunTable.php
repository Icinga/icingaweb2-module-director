<?php

namespace Icinga\Module\Director\Web\Table;

use Icinga\Module\Director\Objects\ImportSource;
use gipfl\IcingaWeb2\Link;
use gipfl\IcingaWeb2\Table\ZfQueryBasedTable;

class ImportrunTable extends ZfQueryBasedTable
{
    use DbHelper;

    /** @var ImportSource */
    protected $source;

    protected $searchColumns = [
        'source_name',
    ];

    public static function load(ImportSource $source)
    {
        $table = new static($source->getConnection());
        $table->source = $source;
        return $table;
    }

    public function getColumnsToBeRendered()
    {
        return [
            $this->translate('Source name'),
            $this->translate('Timestamp'),
            $this->translate('Imported rows'),
        ];
    }

    public function renderRow($row)
    {
        return $this::row([
            Link::create(
                $row->source_name,
                'director/importrun',
                ['id' => $row->id]
            ),
            $row->start_time,
            $row->cnt_rows
        ]);
    }

    public function prepareQuery()
    {
        $db = $this->db();
        $columns = array(
            'id'          => 'r.id',
            'source_id'   => 's.id',
            'source_name' => 's.source_name',
            'start_time'  => 'r.start_time',
            'rowset'      => 'LOWER(HEX(rs.checksum))',
            'cnt_rows'    => 'COUNT(rsr.row_checksum)',
        );

        if ($this->isPgsql()) {
            $columns['rowset'] = "LOWER(ENCODE(rs.checksum, 'hex'))";
        }

        // TODO: Store row count to rowset
        $query = $db->select()->from(
            ['s' => 'import_source'],
            $columns
        )->join(
            ['r' => 'import_run'],
            'r.source_id = s.id',
            []
        )->joinLeft(
            ['rs' => 'imported_rowset'],
            'rs.checksum = r.rowset_checksum',
            []
        )->joinLeft(
            ['rsr' => 'imported_rowset_row'],
            'rs.checksum = rsr.rowset_checksum',
            []
        )->group('r.id')->group('s.id')->group('rs.checksum')
         ->order('r.start_time DESC');

        if ($this->source) {
            $query->where('r.source_id = ?', $this->source->get('id'));
        }

        return $query;
    }
}
