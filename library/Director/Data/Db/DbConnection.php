<?php

namespace Icinga\Module\Director\Data\Db;

use Icinga\Data\Db\DbConnection as IcingaDbConnection;
use Icinga\Module\Director\Db\DbUtil;
use RuntimeException;
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

    /**
     * @deprecated
     * @param ?string $binary
     * @return Zend_Db_Expr|Zend_Db_Expr[]|null
     */
    public function quoteBinary($binary)
    {
        return DbUtil::quoteBinaryLegacy($binary, $this->getDbAdapter());
    }

    public function binaryDbResult($value)
    {
        if (is_resource($value)) {
            return stream_get_contents($value);
        }

        return $value;
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
