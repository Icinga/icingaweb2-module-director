<?php

namespace Icinga\Module\Director;

use Zend_Db_Expr;

class Util
{
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
}
