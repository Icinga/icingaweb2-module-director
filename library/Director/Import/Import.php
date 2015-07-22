<?php

namespace Icinga\Module\Director\Import;

use Icinga\Module\Director\Objects\ImportSource;
use Icinga\Module\Director\Web\Hook\ImportSourceHook;

class Import
{
    protected $db;

    protected function __construct()
    {
    }

    public static function run(ImportSource $source)
    {
        $import = new self();
        return $import->importFromSource($source);
    }

    protected function importFromSource(ImportSource $source)
    {
        $connection = $source->getConnection();
        $this->db = $db = $connection->getDbAdapter();

        $keyColumn = $source->key_column;
        $rows = array();
        $props = array();
        $rowsProps = array();

        foreach (ImportSourceHook::loadByName($source->source_name, $connection)->fetchData() as $row) {
            // TODO: Check for name collision
            if (! isset($row->$keyColumn)) {
                die('Depp');
            }
            $object_name = $row->$keyColumn;
            $rowChecksums = array();
            $keys = array_keys((array) $row);
            sort($keys);

            foreach ($keys as $key) {
                $checksum = sha1($key . '=' . json_encode((string) $row->$key), true);

                if (! array_key_exists($checksum, $props)) {
                    $props[$checksum] = array(
                        'checksum'       => $checksum,
                        'property_name'  => $key,
                        'property_value' => $row->$key,
                        'format'         => 'string'
                    );
                }

                $rowChecksums[] = $checksum;
            }

            $checksum = sha1($object_name . ';' . implode(';', $rowChecksums), true);
            if (array_key_exists($checksum, $rows)) {
                die('WTF, collision?');
            }

            $rows[$checksum] = array(
                'checksum'    => $checksum,
                'object_name' => $object_name
            );

            $rowsProps[$checksum] = $rowChecksums;
        }

        $rowSums = array_keys($rows);
        $rowset = sha1(implode(';', $rowSums), true);

        $db->beginTransaction();
        if (! $this->rowSetExists($rowset)) {

            $newRows = $this->newChecksums('imported_row', $rowSums);
            $newProperties = $this->newChecksums('imported_property', array_keys($props));

            if (! empty($newProperties) || ! empty($newRows)) {
                foreach ($newProperties as $checksum) {
                    $db->insert('imported_property', $props[$checksum]);
                }

                $db->insert('imported_rowset', array('checksum' => $rowset));

                foreach ($newRows as $checksum) {
                    $db->insert('imported_row', $rows[$checksum]);
                    $db->insert(
                        'imported_rowset_row',
                        array(
                            'rowset_checksum' => $rowset,
                            'row_checksum'    => $checksum
                        )
                    );
                    foreach ($rowsProps[$checksum] as $propChecksum) {
                        $db->insert('imported_row_property', array(
                            'row_checksum'      => $checksum,
                            'property_checksum' => $propChecksum
                        ));
                    }
                }
            }
        }
        $db->insert(
            'import_run',
            array(
                'source_id' => $source->id,
                'rowset_checksum' => $rowset,
                'start_time' => date('Y-m-d H:i:s'),
                'succeeded' => 'y'
            )
        );
        $id =  $db->lastInsertId();
        $db->commit();

        return $id;
    }

    protected function rowSetExists($checksum)
    {
        return count($this->newChecksums('imported_rowset', array($checksum))) === 0;
    }

    protected function newChecksums($table, $checksums)
    {
        $db = $this->db;

        $existing = $db->fetchCol(
            $db->select()->from($table, 'checksum')->where('checksum IN (?)', $checksums)
        );

        return array_diff($checksums, $existing);
    }
}
