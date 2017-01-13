<?php

namespace Icinga\Module\Director\Data\Db;

use Icinga\Data\Db\DbConnection as IcingaDbConnection;

class DbConnection extends IcingaDbConnection
{
    public function isPgsql()
    {
        return $this->getDbType() === 'pgsql';
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
