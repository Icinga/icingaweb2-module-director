<?php

namespace Icinga\Module\Director;

use Icinga\Authentication\Manager;
use Zend_Db_Expr;

class Util
{
    protected static $auth;

    public static function pgBinEscape($binary)
    {
        return new \Zend_Db_Expr("'\\x" . bin2hex($binary) . "'");
    }

    public static function hex2binary($bin)
    {
        return pack('H*', $bin);
    }

    public static function binary2hex($hex)
    {
        return current(unpack('H*', $hex));
    }

    public static function auth()
    {
        if (self::$auth === null) {
            self::$auth = Manager::getInstance();
        }
        return self::$auth;
    }

    public static function hasPermission($name)
    {
        return self::auth()->hasPermission($name);
    }

    public static function getRestrictions($name)
    {
        return self::auth()->getRestrictions($name);
    }
}
