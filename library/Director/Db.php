<?php

namespace Icinga\Module\Director;

use Icinga\Data\Db\DbConnection;

class Db extends DbConnection
{
    protected $modules = array();

    protected static $zoneCache;

    protected static $commandCache;

    protected function db()
    {
        return $this->getDbAdapter();
    }

    public function fetchActivityLogEntryById($id)
    {
        $sql = 'SELECT * FROM director_activity_log WHERE id = ' . (int) $id;

        return $this->db()->fetchRow($sql);
    }

    public function fetchActivityLogEntry($checksum)
    {   
        $sql = 'SELECT * FROM director_activity_log WHERE checksum = ?';

        return $this->db()->fetchRow($sql, $checksum);
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

    public function enumCommands()
    {
        if (self::$commandCache === null) {
            $select = $this->db()->select()->from('icinga_command', array(
                'id',
                'object_name',
            ))
              ->order('object_name ASC');

            self::$commandCache = $this->db()->fetchPairs($select);
        }
        return self::$commandCache;
    }

    public function enumZones()
    {
        if (self::$zoneCache === null) {
            $select = $this->db()->select()->from('icinga_zone', array(
                'id',
                'object_name',
            ))->where('object_type', 'object')->order('object_name ASC');

            self::$zoneCache = $this->db()->fetchPairs($select);
        }

        return self::$zoneCache;
    }

    public function getZoneName($id)
    {
        $objects = $this->enumZones();
        return $objects[$id];
    }

    public function getCommandName($id)
    {
        $objects = $this->enumCommands();
        return $objects[$id];
    }

    public function enumHosts()
    {
        $select = $this->db()->select()->from('icinga_host', array(
            'id',
            'object_name',
        ))->where('object_type', 'object')->order('object_name ASC');
        return $this->db()->fetchPairs($select);
    }

    public function enumHostgroups()
    {
        $select = $this->db()->select()->from('icinga_hostgroup', array(
            'id',
            'object_name',
        ))->where('object_type', 'object')->order('object_name ASC');
        return $this->db()->fetchPairs($select);
    }

    public function enumServices()
    {
        $select = $this->db()->select()->from('icinga_service', array(
            'id',
            'object_name',
        ))->where('object_type', 'object')->order('object_name ASC');
        return $this->db()->fetchPairs($select);
    }

    public function enumServicegroups()
    {
        $select = $this->db()->select()->from('icinga_servicegroup', array(
            'id',
            'object_name',
        ))->where('object_type', 'object')->order('object_name ASC');
        return $this->db()->fetchPairs($select);
    }

    public function enumUsers()
    {
        $select = $this->db()->select()->from('icinga_user', array(
            'id',
            'object_name',
        ))->where('object_type', 'object')->order('object_name ASC');
        return $this->db()->fetchPairs($select);
    }

    public function enumUsergroups()
    {
        $select = $this->db()->select()->from('icinga_usergroup', array(
            'id',
            'object_name',
        ))->where('object_type', 'object')->order('object_name ASC');
        return $this->db()->fetchPairs($select);
    }

    public function clearZoneCache()
    {
        // TODO: wipe cache on update/insert/delete
        self::$zoneCache = null;
    }

    public function clearCommandCache()
    {
        // TODO: wipe cache on update/insert/delete
        self::$commandCache = null;
    }
}
