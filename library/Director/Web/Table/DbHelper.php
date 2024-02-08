<?php

namespace Icinga\Module\Director\Web\Table;

use Zend_Db_Expr as Expr;

trait DbHelper
{
    public function dbHexFunc($column)
    {
        if ($this->isPgsql()) {
            return sprintf("LOWER(ENCODE(%s, 'hex'))", $column);
        } else {
            return sprintf("LOWER(HEX(%s))", $column);
        }
    }

    public function quoteBinary($binary)
    {
        if ($binary === '') {
            return '';
        }

        if (is_array($binary)) {
            return array_map([$this, 'quoteBinary'], $binary);
        }

        if ($this->isPgsql()) {
            return new Expr("'\\x" . bin2hex($binary) . "'");
        }

        return new Expr('0x' . bin2hex($binary));
    }

    public function isPgsql()
    {
        return $this->db() instanceof \Zend_Db_Adapter_Pdo_Pgsql;
    }

    public function isMysql()
    {
        return $this->db() instanceof \Zend_Db_Adapter_Pdo_Mysql;
    }

    public function wantBinaryValue($value)
    {
        if (is_resource($value)) {
            return stream_get_contents($value);
        }

        return $value;
    }

    public function getChecksum($checksum)
    {
        return bin2hex($this->wantBinaryValue($checksum));
    }

    public function getShortChecksum($checksum)
    {
        if ($checksum === null) {
            return null;
        }

        return substr($this->getChecksum($checksum), 0, 7);
    }
}
