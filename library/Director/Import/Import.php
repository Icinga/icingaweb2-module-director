<?php

namespace Icinga\Module\Director\Import;

use Icinga\Module\Director\Objects\ImportSource;
use Icinga\Module\Director\Util;
use Icinga\Module\Director\Web\Hook\ImportSourceHook;
use Icinga\Exception\IcingaException;
use stdClass;

class Import
{
    /**
     * @var ImportSource
     */
    protected $source;

    /**
     * @var Icinga\Data\Db\DbConnection
     */
    protected $connection;

    /**
     * @var Zend_Db_Adapter_Abstract
     */
    protected $db;

    protected $data;

    protected function __construct(ImportSource $source)
    {
        $this->source = $source;
        $this->connection = $source->getConnection();
        $this->db = $connection->getDbAdapter();
    }

    public static function run(ImportSource $source)
    {
        $import = new self($source);
        return $import->importFromSource($source);
    }

    protected function rawData()
    {
        if ($this->data === null) {
            $this->data = ImportSourceHook::loadByName(
                $this->source->source_name,
                $this->connection
            )->fetchData();
        }

        return & $this->data;
    }

    protected function importFromSource()
    {
        $source = $this->source;
        $connection = $source->getConnection();
        $this->db = $db = $connection->getDbAdapter();

        $keyColumn = $source->key_column;
        $rows = array();
        $props = array();
        $rowsProps = array();

        foreach ($this->rawData() as $row) {
            // TODO: Check for name collision
            if (! isset($row->$keyColumn)) {
                // TODO: re-enable errors
                continue;
                throw new IcingaException(
                    'No key column "%s" in row: %s',
                    $keyColumn,
                    json_encode($row)
                );
            }

            $object_name = $row->$keyColumn;
            $rowChecksums = array();
            $keys = array_keys((array) $row);
            sort($keys);

            foreach ($keys as $key) {

                // TODO: Specify how to treat NULL values. Ignoring for now.
                if ($row->$key === null) {
                    continue;
                }

                $pval = $row->$key;
                if (is_array($pval)) {
                    $pval = json_encode($pval);
                    $format = 'json';
                } elseif ($pval instanceof stdClass) {
                    $pval = json_encode($this->sortObject($pval));
                    $format = 'json';
                } else {
                    $format = 'string';
                }

                $checksum = sha1(sprintf('%s=(%s)%s', $key, $format, $pval), true);

                if (! array_key_exists($checksum, $props)) {
                    $props[$checksum] = array(
                        'checksum'       => $checksum,
                        'property_name'  => $key,
                        'property_value' => $pval,
                        'format'         => $format
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

        if ($this->rowSetExists($rowset)) {
            if ($this->lastRowsetIs($rowset)) {
                return false;
            }
        }

        $db->beginTransaction();
        if (! $this->rowSetExists($rowset)) {

            if (empty($rowSums)) {
                $newRows = array();
            } else {
                $newRows = $this->newChecksums('imported_row', $rowSums);
            }

            if (empty($rowSums)) {
                $newProperties = array();
            } else {
                $newProperties = $this->newChecksums('imported_property', array_keys($props));
            }

            $db->insert('imported_rowset', array('checksum' => $rowset));

            foreach ($newProperties as $checksum) {
                $db->insert('imported_property', $props[$checksum]);
            }

            foreach ($newRows as $checksum) {
                $db->insert('imported_row', $rows[$checksum]);
                foreach ($rowsProps[$checksum] as $propChecksum) {
                    $db->insert('imported_row_property', array(
                        'row_checksum'      => $checksum,
                        'property_checksum' => $propChecksum
                    ));
                }
            }

            foreach (array_keys($rows) as $checksum) {
                $db->insert(
                    'imported_rowset_row',
                    array(
                        'rowset_checksum' => $rowset,
                        'row_checksum'    => $checksum
                    )
                );
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

    /**
     * Whether the last run of this import matches the given checksum
     */
    protected function lastRowsetIs($checksum)
    {
        return $this->connection->getLatestImportedChecksum($this->source->id)
            === Util::binary2hex($checksum);
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

    /**
     * Sort a given stdClass object by property name
     */
    protected function sortObject($object)
    {
        $array = (array) $object;
        foreach ($array as $key => $val) {
            $this->sortElement($val);
        }
        ksort($array);
        return (object) $array;
    }

    /**
     * Walk through a given array and sort all children
     *
     * Please note that the array itself will NOT be sorted, as arrays must
     * keep their ordering
     */
    protected function sortArrayObject(& $array)
    {
        foreach ($array as $key => $val) {
            $this->sortElement($val);
        }
    }

    /**
     * Recursively sort a given property
     */
    protected function sortElement(& $el)
    {
        if (is_array($el)) {
            $this->sortArrayObject($el);
        } elseif ($el instanceof stdClass) {
            $el = $this->sortObject($el);
        }
    }
}
