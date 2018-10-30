<?php

namespace Icinga\Module\Director\Data\Db;

use Icinga\Data\Db\DbConnection as IcingaDbConnection;
use Zend_Db_Expr;

class DbConnection extends IcingaDbConnection
{
    public function isMysql()
    {
        return $this->getDbType() === 'mysql';
    }

    public function isPgsql()
    {
        return $this->getDbType() === 'pgsql';
    }

    public function quoteBinary($binary)
    {
        if ($this->isPgsql()) {
            return new Zend_Db_Expr("'\\x" . bin2hex($binary) . "'");
        }

        return $binary;
    }

    public function hasPgExtension($name)
    {
        $db = $this->db();
        $query = $db->select()->from(
            array('e' => 'pg_extension'),
            array('cnt' => 'COUNT(*)')
        )->where('extname = ?', $name);

        return (int) $db->fetchOne($query) === 1;
    }
}
