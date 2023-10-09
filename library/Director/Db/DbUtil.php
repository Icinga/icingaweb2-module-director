<?php

namespace Icinga\Module\Director\Db;

use gipfl\ZfDb\Adapter\Adapter;
use gipfl\ZfDb\Adapter\Pdo\Pgsql;
use gipfl\ZfDb\Expr;
use Zend_Db_Adapter_Abstract;
use Zend_Db_Adapter_Pdo_Pgsql;
use Zend_Db_Expr;
use function bin2hex;
use function is_array;
use function is_resource;
use function stream_get_contents;

class DbUtil
{
    public static function binaryResult($value)
    {
        if (is_resource($value)) {
            return stream_get_contents($value);
        }

        return $value;
    }


    /**
     * @param string|array $binary
     * @param Zend_Db_Adapter_Abstract $db
     * @return Zend_Db_Expr|Zend_Db_Expr[]
     */
    public static function quoteBinaryLegacy($binary, $db)
    {
        if (is_array($binary)) {
            return static::quoteArray($binary, 'quoteBinaryLegacy', $db);
        }

        if ($binary === null) {
            return null;
        }

        if ($db instanceof Zend_Db_Adapter_Pdo_Pgsql) {
            return new Zend_Db_Expr("'\\x" . bin2hex($binary) . "'");
        }

        return new Zend_Db_Expr('0x' . bin2hex($binary));
    }

    /**
     * @param string|array $binary
     * @param Adapter $db
     * @return Expr|Expr[]
     */
    public static function quoteBinary($binary, $db)
    {
        if (is_array($binary)) {
            return static::quoteArray($binary, 'quoteBinary', $db);
        }

        if ($binary === null) {
            return null;
        }

        if ($db instanceof Pgsql) {
            return new Expr("'\\x" . bin2hex($binary) . "'");
        }

        return new Expr('0x' . bin2hex($binary));
    }

    /**
     * @param string|array $binary
     * @param Adapter|Zend_Db_Adapter_Abstract $db
     * @return Expr|Zend_Db_Expr|Expr[]|Zend_Db_Expr[]
     */
    public static function quoteBinaryCompat($binary, $db)
    {
        if ($db instanceof Adapter) {
            return static::quoteBinary($binary, $db);
        }

        return static::quoteBinaryLegacy($binary, $db);
    }

    protected static function quoteArray($array, $method, $db)
    {
        $result = [];
        foreach ($array as $bin) {
            $quoted = static::$method($bin, $db);
            $result[] = $quoted;
        }

        return $result;
    }
}
