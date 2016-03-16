<?php

namespace Icinga\Module\Director\Import;

use Icinga\Exception\IcingaException;
use Icinga\Module\Director\Hook\ImportSourceHook;
use Icinga\Module\Director\Objects\ImportSource;
use Icinga\Module\Director\Util;
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

    /**
     * Raw data that should be imported, array of stdClass objects
     *
     * @var array
     */
    protected $data;

    /**
     * Checksum of the rowset that should be imported
     *
     * @var string
     */
    private $rowsetChecksum;

    /**
     * Checksum-indexed rows
     *
     * @var array
     */
    private $rows;

    /**
     * Checksum-indexed row -> property
     *
     * @var array
     */
    private $rowProperties;

    /**
     * Whether this rowset exists, for caching purposes
     *
     * @var boolean
     */
    private $rowsetExists;

    protected $properties = array();

    /**
     * Checksums of all rows
     */
    private $rowChecksums;

    public function __construct(ImportSource $source)
    {
        $this->source = $source;
        $this->connection = $source->getConnection();
        $this->db = $this->connection->getDbAdapter();
    }

    /**
     * Whether this import provides modified data
     *
     * @return boolean
     */
    public function providesChanges()
    {
        return ! $this->rowsetExists()
            || ! $this->lastRowsetIs($this->rowsetChecksum());
    }

    /**
     * Trigger an import run
     *
     * @return int Last import run ID
     */
    public function run()
    {
        if ($this->providesChanges() && ! $this->rowsetExists()) {
            $this->storeRowset();
        }

        $this->db->insert(
            'import_run',
            array(
                'source_id'       => $this->source->id,
                'rowset_checksum' => $this->quoteBinary($this->rowsetChecksum()),
                'start_time'      => date('Y-m-d H:i:s'),
                'succeeded'       => 'y'
            )
        );
        if ($this->connection->isPgsql()) {
            return $this->db->lastInsertId('import_run', 'id');
        } else {
            return $this->db->lastInsertId();
        }
    }

    /**
     * Whether there are no rows to be fetched from import source
     *
     * @return boolean
     */
    public function isEmpty()
    {
        $rows = $this->checksummedRows();
        return empty($rows);
    }

    /**
     * Checksum of all available rows
     *
     * @return string
     */
    protected function & rowsetChecksum()
    {
        if ($this->rowsetChecksum === null) {
            $this->prepareChecksummedRows();
        }

        return $this->rowsetChecksum;
    }

    /**
     * Checksum of all available rows
     *
     * @return string
     */
    protected function & checksummedRows()
    {
        if ($this->rows === null) {
            $this->prepareChecksummedRows();
        }

        return $this->rows;
    }

    /**
     * Checksum of all available rows
     *
     * @return string
     */
    protected function & rawData()
    {
        if ($this->data === null) {
            $this->data = ImportSourceHook::loadByName(
                $this->source->source_name,
                $this->connection
            )->fetchData();
        }

        $modifiers = $this->prepareModifiers();

        if (empty($modifiers)) {
            return $this->data;
        }

        foreach ($this->data as & $row) {
            foreach ($modifiers as $key => $mods) {
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

        return $this->data;
    }

    /**
     * Prepare and remember an ImportedProperty
     *
     * @return array
     */
    protected function prepareImportedProperty($key, $rawValue)
    {
        if (is_array($rawValue)) {
            $value = json_encode($rawValue);
            $format = 'json';
        } elseif ($rawValue instanceof stdClass) {
            $value = json_encode($this->sortObject($rawValue));
            $format = 'json';
        } else {
            $value = $rawValue;
            $format = 'string';
        }

        $checksum = sha1(sprintf('%s=(%s)%s', $key, $format, $value), true);

        if (! array_key_exists($checksum, $this->properties)) {
            $this->properties[$checksum] = array(
                'checksum'       => $this->quoteBinary($checksum),
                'property_name'  => $key,
                'property_value' => $value,
                'format'         => $format
            );
        }

        return $this->properties[$checksum];
    }

    // TODO: move to ImportSource, this duplicates logic from preview
    protected function prepareModifiers()
    {
        $modifiers = $this->source->fetchRowModifiers();
        $propertyModifiers = array();

        foreach ($modifiers as $mod) {
            if (! array_key_exists($mod->property_name, $propertyModifiers)) {
                $propertyModifiers[$mod->property_name] = array();
            }
            $obj = new $mod->provider_class;
            $obj->setSettings($mod->getSettings());
            $obj->setDb($this->connection);
            $propertyModifiers[$mod->property_name][] = $obj;
        }

        return $propertyModifiers;
    }

    /**
     * Walk through each row, prepare properties and calculate checksums
     */
    protected function prepareChecksummedRows()
    {
        $keyColumn = $this->source->key_column;
        $this->rows = array();
        $this->rowProperties = array();
        $objects = array();
        $rowCount = 0;

        foreach ($this->rawData() as $row) {
            $rowCount++;

            // Key column must be set
            if (! isset($row->$keyColumn)) {
                throw new IcingaException(
                    'No key column "%s" in row %d',
                    $keyColumn,
                    $rowCount
                );
            }

            $object_name = $row->$keyColumn;

            // Check for name collision
            if (array_key_exists($object_name, $objects)) {
                throw new IcingaException(
                    'Duplicate entry: %s',
                    $object_name
                );
            }

            $rowChecksums = array();
            $keys = array_keys((array) $row);
            sort($keys);

            foreach ($keys as $key) {
                // TODO: Specify how to treat NULL values. Ignoring for now.
                //       One option might be to import null (checksum '(null)')
                //       and to provide a flag at sync time
                if ($row->$key === null) {
                    continue;
                }

                $property = $this->prepareImportedProperty($key, $row->$key);
                $rowChecksums[] = $property['checksum'];
            }

            $checksum = sha1($object_name . ';' . implode(';', $rowChecksums), true);
            if (array_key_exists($checksum, $this->rows)) {
                die('WTF, collision?');
            }

            $this->rows[$checksum] = array(
                'checksum'    => $this->quoteBinary($checksum),
                'object_name' => $object_name
            );

            $this->rowProperties[$checksum] = $rowChecksums;

            $objects[$object_name] = $checksum;
        }

        $this->rowChecksums = array_keys($this->rows);
        $this->rowsetChecksum = sha1(implode(';', $this->rowChecksums), true);
        return $this;
    }

    /**
     * Store our new rowset
     */
    protected function storeRowset()
    {
        $db = $this->db;
        $rowset = $this->rowsetChecksum();
        $rows = $this->checksummedRows();

        $db->beginTransaction();

        if ($this->isEmpty()) {
            $newRows = array();
            $newProperties = array();
        } else {
            $newRows = $this->newChecksums('imported_row', $this->rowChecksums);
            $newProperties = $this->newChecksums('imported_property', array_keys($this->properties));
        }

        $db->insert('imported_rowset', array('checksum' => $this->quoteBinary($rowset)));

        foreach ($newProperties as $checksum) {
            $db->insert('imported_property', $this->properties[$checksum]);
        }

        foreach ($newRows as $row) {
            $db->insert('imported_row', $rows[$row]);
            foreach ($this->rowProperties[$row] as $property) {
                $db->insert('imported_row_property', array(
                    'row_checksum'      => $this->quoteBinary($row),
                    'property_checksum' => $property
                ));
            }
        }

        foreach (array_keys($rows) as $row) {
            $db->insert(
                'imported_rowset_row',
                array(
                    'rowset_checksum' => $this->quoteBinary($rowset),
                    'row_checksum'    => $this->quoteBinary($row)
                )
            );
        }

        $db->commit();

        $this->rowsetExists = true;
    }

    /**
     * Whether the last run of this import matches the given checksum
     */
    protected function lastRowsetIs($checksum)
    {
        return $this->connection->getLatestImportedChecksum($this->source->id)
            === Util::binary2hex($checksum);
    }

    /**
     * Whether our rowset already exists in the database
     *
     * @return boolean
     */
    protected function rowsetExists()
    {
        if (null === $this->rowsetExists) {
            $this->rowsetExists = 0 === count(
                $this->newChecksums(
                    'imported_rowset',
                    array($this->rowsetChecksum())
                )
            );
        }

        return $this->rowsetExists;
    }

    /**
     * Finde new checksums for a specific table
     *
     * Accepts an array of checksums and gives you an array with those checksums
     * that are missing in the given table
     *
     * @param string $table Database table name
     * @param array  $checksums Array with the checksums that should be verified
     *
     * @return array
     */
    protected function newChecksums($table, $checksums)
    {
        $db = $this->db;

        // TODO: The following is a quickfix for binary data corrpution reported
        //       in https://github.com/zendframework/zf1/issues/655 caused by
        //       https://github.com/zendframework/zf1/commit/2ac9c30f
        //
        // Should be reverted once fixed, eventually with a check continueing
        // to use this workaround for specific ZF versions (1.12.16 and 1.12.17
        // so far). Alternatively we could also use a custom quoteInto method.

        // The former query looked as follows:
        //
        // $query = $db->select()->from($table, 'checksum')
        //     ->where('checksum IN (?)', $checksums)
        // ...
        // return array_diff($checksums, $existing);

        $hexed = array_map('Icinga\Module\Director\Util::binary2hex', $checksums);

        $conn = $this->connection;
        $query = $db
            ->select()
            ->from(
                array('c' => $table),
                array('checksum' => $conn->dbHexFunc('c.checksum'))
            )->where(
                $conn->dbHexFunc('c.checksum') . ' IN (?)',
                $hexed
            );

        $existing = $db->fetchCol($query);
        $new = array_diff($hexed, $existing);

        return array_map('Icinga\Module\Director\Util::hex2binary', $new);
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

    protected function quoteBinary($bin)
    {
        return $this->connection->quoteBinary($bin);
    }
}
