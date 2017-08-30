<?php

namespace Icinga\Module\Director\Test;

use Icinga\Module\Director\Hook\ImportSourceHook;

class ImportSourceDummy extends ImportSourceHook
{
    protected static $rows = array();

    /**
     * Returns an array containing importable objects
     *
     * @return array
     */
    public function fetchData()
    {
        return self::$rows;
    }

    /**
     * Returns a list of all available columns
     *
     * @return array
     */
    public function listColumns()
    {
        $keys = array();
        foreach (self::$rows as $row) {
            $keys = array_merge($keys, array_keys($row));
        }
        return $keys;
    }

    public static function clearRows()
    {
        self::$rows = array();
    }

    public static function setRows($rows)
    {
        static::clearRows();
        foreach ($rows as $row) {
            static::addRow($row);
        }
    }

    public static function addRow($row)
    {
        self::$rows[] = (object) $row;
    }
}
