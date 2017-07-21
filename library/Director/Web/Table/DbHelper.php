<?php

namespace Icinga\Module\Director\Web\Table;

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
        if ($this->isPgsql()) {
            return new Expr("'\\x" . bin2hex($binary) . "'");
        }

        return $binary;
    }

    public function isPgsql()
    {
        return $this->db->getConfig() instanceof \Zend_Db_Adapter_Pdo_Pgsql;
    }

    public function isMysql()
    {
        return $this->db->getConfig() instanceof \Zend_Db_Adapter_Pdo_Mysql;
    }

    public function wantBinaryValue($value)
    {
        if (is_resource($value)) {
            return stream_get_contents($value);
        }

        return $value;
    }
}
