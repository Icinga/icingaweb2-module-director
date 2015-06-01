<?php

namespace Icinga\Module\Director;

use Icinga\Data\Db\DbConnection;

class Db extends DbConnection
{
    protected $modules = array();

    protected function db()
    {
        return $this->getDbAdapter();
    }

    public function fetchActivityLogEntry($id)
    {
        $sql = 'SELECT * FROM director_activity_log WHERE id = ' . (int) $id;

        return $this->db()->fetchRow($sql);
    }

    public function getLastActivityChecksum()
    {
        $select = "SELECT checksum FROM (SELECT * FROM (SELECT 1 AS pos, LOWER(HEX(checksum)) AS checksum FROM director_activity_log ORDER BY change_time DESC LIMIT 1) a UNION SELECT 2 AS pos, '' AS checksum) u ORDER BY pos LIMIT 1";

        return $this->db()->fetchOne($select);
    }

    public function enumCheckcommands()
    {
        $select = $this->db()->select()->from('icinga_command', array(
            'id',
            'object_name',
        ))->where('object_type IN (?)', array('object', 'external_object'))
          ->where('methods_execute IN (?)', array('PluginCheck', 'IcingaCheck'))
          ->order('object_name ASC');
        return $this->db()->fetchPairs($select);
    }

    public function enumZones()
    {
        $select = $this->db()->select()->from('icinga_zone', array(
            'id',
            'object_name',
        ))->where('object_type', 'object')->order('object_name ASC');
        return $this->db()->fetchPairs($select);
    }

    public function enumHosts()
    {
        $select = $this->db()->select()->from('icinga_host', array(
            'id',
            'object_name',
        ))->where('object_type', 'object')->order('object_name ASC');
        return $this->db()->fetchPairs($select);
    }
}
