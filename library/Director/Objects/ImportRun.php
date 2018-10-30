<?php

namespace Icinga\Module\Director\Objects;

use Icinga\Module\Director\Data\Db\DbObject;
use Icinga\Module\Director\Db;

class ImportRun extends DbObject
{
    protected $table = 'import_run';

    protected $keyName = 'id';

    protected $autoincKeyName = 'id';

    /** @var ImportSource */
    protected $importSource = null;

    protected $defaultProperties = [
        'id'              => null,
        'source_id'       => null,
        'rowset_checksum' => null,
        'start_time'      => null,
        'end_time'        => null,
        // TODO: Check whether succeeded could be dropped
        'succeeded'       => null,
    ];

    protected $binaryProperties = [
        'rowset_checksum',
    ];

    public function prepareImportedObjectQuery($columns = array('object_name'))
    {
        return $this->getDb()->select()->from(
            array('r' => 'imported_row'),
            $columns
        )->joinLeft(
            array('rsr' => 'imported_rowset_row'),
            'rsr.row_checksum = r.checksum',
            array()
        )->where(
            'rsr.rowset_checksum = ?',
            $this->getConnection()->quoteBinary($this->rowset_checksum)
        );
    }

    public function listColumnNames()
    {
        $db = $this->getDb();

        $query = $db->select()->distinct()->from(
            array('p' => 'imported_property'),
            'property_name'
        )->join(
            array('rp' => 'imported_row_property'),
            'rp.property_checksum = p.checksum',
            array()
        )->join(
            array('rsr' => 'imported_rowset_row'),
            'rsr.row_checksum = rp.row_checksum',
            array()
        )->where('rsr.rowset_checksum = ?', $this->getConnection()->quoteBinary($this->rowset_checksum));

        return $db->fetchCol($query);
    }

    public function fetchRows($columns, $filter = null, $keys = null)
    {
        $db = $this->getDb();
        /** @var Db $connection */
        $connection = $this->getConnection();
        $binchecksum = $this->rowset_checksum;

        $query = $db->select()->from(
            array('rsr' => 'imported_rowset_row'),
            array(
                'object_name'    => 'r.object_name',
                'property_name'  => 'p.property_name',
                'property_value' => 'p.property_value',
                'format'         => 'p.format'
            )
        )->join(
            array('r' => 'imported_row'),
            'rsr.row_checksum = r.checksum',
            array()
        )->join(
            array('rp' => 'imported_row_property'),
            'r.checksum = rp.row_checksum',
            array()
        )->join(
            array('p' => 'imported_property'),
            'p.checksum = rp.property_checksum',
            array()
        )->order('r.object_name');
        if ($connection->isMysql()) {
            $query->where('rsr.rowset_checksum = :checksum')->bind([
                'checksum' => $binchecksum
            ]);
        } else {
            $query->where(
                'rsr.rowset_checksum = ?',
                $connection->quoteBinary($binchecksum)
            );
        }

        if ($columns === null) {
            $columns = $this->listColumnNames();
        } else {
            $query->where('p.property_name IN (?)', $columns);
        }

        $result = array();
        $empty = (object) array();
        foreach ($columns as $k => $v) {
            $empty->$k = null;
        }

        if ($keys !== null) {
            $query->where('r.object_name IN (?)', $keys);
        }

        foreach ($db->fetchAll($query) as $row) {
            if (! array_key_exists($row->object_name, $result)) {
                $result[$row->object_name] = clone($empty);
            }

            if ($row->format === 'json') {
                $result[$row->object_name]->{$row->property_name} = json_decode($row->property_value);
            } else {
                $result[$row->object_name]->{$row->property_name} = $row->property_value;
            }
        }

        if ($filter) {
            $filtered = array();
            foreach ($result as $key => $row) {
                if ($filter->matches($row)) {
                    $filtered[$key] = $row;
                }
            }

            return $filtered;
        }

        return $result;
    }

    public function importSource()
    {
        if ($this->importSource === null) {
            $this->importSource = ImportSource::loadWithAutoIncId(
                (int) $this->get('source_id'),
                $this->connection
            );
        }
        return $this->importSource;
    }
}
