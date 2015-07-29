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
        if ($this->getDbType() === 'pgsql') {
            $checksum = new \Zend_Db_Expr("\\x" . bin2hex($checksum));
        }

        $sql = 'SELECT * FROM director_activity_log WHERE checksum = ?';
        $ret = $this->db()->fetchRow($sql, $checksum);

        if (is_resource($ret->checksum)) {
            $ret->checksum = stream_get_contents($ret->checksum);
        }

        if (is_resource($ret->parent_checksum)) {
            $ret->checksum = stream_get_contents($ret->parent_checksum);
        }

        return $ret;
    }

    public function getLastActivityChecksum()
    {
        if ($this->getDbType() === 'pgsql') {
            $select = "SELECT checksum FROM (SELECT * FROM (SELECT 1 AS pos, LOWER(ENCODE(checksum, 'hex')) AS checksum FROM director_activity_log ORDER BY change_time DESC LIMIT 1) a UNION SELECT 2 AS pos, '' AS checksum) u ORDER BY pos LIMIT 1";
        } else {
            $select = "SELECT checksum FROM (SELECT * FROM (SELECT 1 AS pos, LOWER(HEX(checksum)) AS checksum FROM director_activity_log ORDER BY change_time DESC LIMIT 1) a UNION SELECT 2 AS pos, '' AS checksum) u ORDER BY pos LIMIT 1";
        }

        return $this->db()->fetchOne($select);
    }

    public function fetchImportStatistics()
    {
        $query = "SELECT 'imported_properties' AS stat_name, COUNT(*) AS stat_value"
          . "   FROM import_run i"
          . "   JOIN imported_rowset_row rs ON i.rowset_checksum = rs.rowset_checksum"
          . "   JOIN imported_row_property rp ON rp.row_checksum = rs.row_checksum"
          . "  UNION ALL"
          . " SELECT 'imported_rows' AS stat_name, COUNT(*) AS stat_value"
          . "   FROM import_run i"
          . "   JOIN imported_rowset_row rs ON i.rowset_checksum = rs.rowset_checksum"
          . "  UNION ALL"
          . " SELECT 'unique_rows' AS stat_name, COUNT(*) AS stat_value"
          . "   FROM imported_row"
          . "  UNION ALL"
          . " SELECT 'unique_properties' AS stat_name, COUNT(*) AS stat_value"
          . "   FROM imported_property"
          ;
        return $this->db()->fetchPairs($query);
    }

    public function getImportrunRowsetChecksum($id)
    {
        $db = $this->db();
        $query = $db->select()
            ->from('import_run', 'rowset_checksum')
            ->where('id = ?', $id);

        return $db->fetchOne($query);
    }

    public function fetchHostTemplateTree()
    {
        $db = $this->db();
        $query = $db->select()->from(
            array('ph' => 'icinga_host'),
            array(
                'host'   => 'h.object_name',
                'parent' => 'ph.object_name'
            )
        )->join(
            array('hi' => 'icinga_host_inheritance'),
            'ph.id = hi.parent_host_id',
            array()
        )->join(
            array('h' => 'icinga_host'),
            'h.id = hi.host_id',
            array()
        )->where("h.object_type = 'template'")
         ->order('ph.object_name')
         ->order('h.object_name');

        $relations = $db->fetchAll($query);
        $children = array();
        $hosts = array();
        foreach ($relations as $rel) {
            foreach (array('host', 'parent') as $col) {
                if (! array_key_exists($rel->$col, $hosts)) {
                    $hosts[$rel->$col] = (object) array(
                        'name'     => $rel->$col,
                        'children' => array()
                    );
                }
            }
        }
        foreach ($relations as $rel) {
            $hosts[$rel->parent]->children[$rel->host] = $hosts[$rel->host];
            $children[$rel->host] = $rel->parent;
        }

        foreach ($children as $name => $host) {
            unset($hosts[$name]);
        }

        return $hosts;
    }

    public function fetchLatestImportedRows($source, $columns = null)
    {
        $db = $this->db();
        $lastRun = $db->select()->from('import_run', array('rowset_checksum'));

        if (is_int($source) || ctype_digit($source)) {
            $lastRun->where('source_id = ?', $source);
        } else {
            $lastRun->where('source_name = ?', $source);
        }

        $lastRun->order('start_time DESC')->limit(1);
        $checksum = $db->fetchOne($lastRun);

        return $this->fetchImportedRowsetRows($checksum, $columns);
    }

    public function listImportedRowsetColumnNames($checksum)
    {
        $db = $this->db();

        $query = $db->select()->distinct()->from(
            array('p' => 'imported_property'),
            'property_name'
        )->join(
            array('rp' => 'imported_row_property'),
            'rp.property_checksum = p.checksum',
            array()
        )->join(
            array('rsr' => 'imported_rowset_row'),
            'rsr.row_checksum = rp.row_checksum',
            array()
        )->where('rsr.rowset_checksum = ?', $checksum);

        return $db->fetchCol($query);
    }

    public function createImportedRowsetRowsQuery($checksum, $columns = null)
    {
        $db = $this->db();

        $query = $db->select()->from(
            array('r' => 'imported_row'),
            array()
        )->join(
            array('rsr' => 'imported_rowset_row'),
            'rsr.row_checksum = r.checksum',
            array()
        )->where('rsr.rowset_checksum = ?', $checksum);

        $propertyQuery = $db->select()->from(
            array('rp' => 'imported_row_property'),
            array(
                'property_value' => 'p.property_value',
                'row_checksum'   => 'rp.row_checksum'
            )
        )->join(
            array('p' => 'imported_property'),
            'rp.property_checksum = p.checksum',
            array()
        );

        $fetchColumns = array('object_name' => 'r.object_name');
        if ($columns === null) {
            $columns = $this->listImportedRowsetColumnNames($checksum);
        }

        foreach ($columns as $c) {
            $fetchColumns[$c] = sprintf('p_%s.property_value', $c);
            $p = clone($propertyQuery);
            $query->joinLeft(
                array(sprintf('p_' . $c) => $p->where('p.property_name = ?', $c)),
                sprintf('p_%s.row_checksum = r.checksum', $c),
                array()
            );
        }

        $query->columns($fetchColumns);

        return $query;
    }

    public function fetchImportedRowsetRows($checksum, $columns = null)
    {
        return $this->db()->fetchAll(
            $this->createImportedRowsetRowsQuery($checksum, $columns)
        );
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
            ))->where('object_type = ?', 'object')->order('object_name ASC');

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
        ))->where('object_type = ?', 'object')->order('object_name ASC');
        return $this->db()->fetchPairs($select);
    }

    public function enumHostTemplates()
    {
        $select = $this->db()->select()->from('icinga_host', array(
            'id',
            'object_name',
        ))->where('object_type = ?', 'template')->order('object_name ASC');
        return $this->db()->fetchPairs($select);
    }

    public function enumSyncRule()
    {
        $select = $this->db()->select()->from('sync_rule', array(
            'id',
            'rule_name'
        ))->order('rule_name ASC');

        return $this->db()->fetchPairs($select);
    }

    public function enumHostgroups()
    {
        $select = $this->db()->select()->from('icinga_hostgroup', array(
            'id',
            'object_name',
        ))->where('object_type = ?', 'object')->order('object_name ASC');
        return $this->db()->fetchPairs($select);
    }

    public function enumServices()
    {
        $select = $this->db()->select()->from('icinga_service', array(
            'id',
            'object_name',
        ))->where('object_type = ?', 'object')->order('object_name ASC');
        return $this->db()->fetchPairs($select);
    }

    public function enumServiceTemplates()
    {
        $select = $this->db()->select()->from('icinga_service', array(
            'id',
            'object_name',
        ))->where('object_type = ?', 'template')->order('object_name ASC');
        return $this->db()->fetchPairs($select);
    }

    public function enumServicegroups()
    {
        $select = $this->db()->select()->from('icinga_servicegroup', array(
            'id',
            'object_name',
        ))->where('object_type = ?', 'object')->order('object_name ASC');
        return $this->db()->fetchPairs($select);
    }

    public function enumUsers()
    {
        $select = $this->db()->select()->from('icinga_user', array(
            'id',
            'object_name',
        ))->where('object_type = ?', 'object')->order('object_name ASC');
        return $this->db()->fetchPairs($select);
    }

    public function enumImportSource()
    {
        $select = $this->db()->select()->from('import_source', array(
            'id',
            'source_name',
        ))->order('source_name ASC');
        return $this->db()->fetchPairs($select);
    }

    public function enumDatalist()
    {
        $select = $this->db()->select()->from('director_datalist', array(
            'id',
            'list_name',
        ))->order('list_name ASC');
        return $this->db()->fetchPairs($select);
    }

    public function enumDatafields()
    {
        $select = $this->db()->select()->from('director_datafield', array(
            'id',
            'varname',
            'caption',
        ))->order('varname ASC');
        return $this->db()->fetchPairs($select);
    }

    public function enumUsergroups()
    {
        $select = $this->db()->select()->from('icinga_usergroup', array(
            'id',
            'object_name',
        ))->where('object_type = ?', 'object')->order('object_name ASC');
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
