<?php

namespace Icinga\Module\Director;

use Icinga\Data\Db\DbConnection;
use Icinga\Module\Director\Objects\DirectorDeploymentLog;
use Icinga\Module\Director\Objects\IcingaEndpoint;
use Icinga\Module\Director\Objects\IcingaObject;
use Icinga\Module\Director\Util;
use Icinga\Exception\ConfigurationError;
use Zend_Db_Expr;
use Zend_Db_Select;

class Db extends DbConnection
{
    protected $modules = array();

    protected static $zoneCache;

    protected static $commandCache;

    protected $settings;

    protected function db()
    {
        return $this->getDbAdapter();
    }

    public function countActivitiesSinceLastDeployedConfig(IcingaObject $object = null)
    {
        $db = $this->db();

        $query = 'SELECT COUNT(*) FROM director_activity_log WHERE id > ('
            . ' SELECT id FROM director_activity_log WHERE checksum = ('
            . '  SELECT last_activity_checksum FROM director_generated_config WHERE checksum = ('
            . '   SELECT config_checksum FROM director_deployment_log ORDER by id desc limit 1'
            . '  )'
            . ' )'
            . ')';

        if ($object !== null) {
            $query .= $db->quoteInto(' AND object_type = ?', $object->getTableName());
            $query .= $db->quoteInto(' AND object_name = ?', $object->object_name);
        }
        return (int) $db->fetchOne($query);
    }

    public function getMasterZoneName()
    {
        if ($zone = $this->getSetting('master_zone')) {
            return $zone;
        }

        $db = $this->db();
        $query = $db->select()
            ->from('icinga_zone', 'object_name')
            ->where('is_global = ?', 'n');

        $zones = $db->fetchCol($query);

        if (count($zones) === 1) {
            return $zones[0];
        }

        return 'master';
    }

    public function getDefaultGlobalZoneName()
    {
        return $this->getSetting('default_global_zone', 'director-global');
    }

    public function hasDeploymentEndpoint()
    {
        $db = $this->db();
        $query = $db->select()->from(
            array('z' => 'icinga_zone'),
            array('cnt' => 'COUNT(*)')
        )->join(
            array('e' => 'icinga_endpoint'),
            'e.zone_id = z.id',
            array()
        )->join(
            array('au' => 'icinga_apiuser'),
            'e.apiuser_id = au.id',
            array()
        )->where('z.object_name = ?', $this->getMasterZoneName());

        return $db->fetchOne($query) > 0;
    }

    public function getDeploymentEndpointName()
    {
        $db = $this->db();
        $query = $db->select()->from(
            array('z' => 'icinga_zone'),
            array('object_name' => 'e.object_name')
        )->join(
            array('e' => 'icinga_endpoint'),
            'e.zone_id = z.id',
            array()
        )->join(
            array('au' => 'icinga_apiuser'),
            'e.apiuser_id = au.id',
            array()
        )->where('z.object_name = ?', $this->getMasterZoneName())
         ->order('e.object_name ASC')
         ->limit(1);

        $name = $db->fetchOne($query);

        if (! $name) {
            throw new ConfigurationError(
                'Unable to detect your deployment endpoint. I was looking for'
                . ' the first endpoint configured with an assigned API user'
                . ' in the "%s" zone.',
                $this->getMasterZoneName()
            );
        }

        return $name;
    }

    public function getDeploymentEndpoint()
    {
        return IcingaEndpoint::load($this->getDeploymentEndpointName(), $this);
    }

    public function getSetting($name, $default = null)
    {
        if ($this->settings === null) {
            $this->fetchSettings();
        }

        if (array_key_exists($name, $this->settings)) {
            return $this->settings[$name];
        }

        return $default;
    }

    public function storeSetting($name, $value)
    {
        $db = $this->db();
        $updated = $db->update(
            'director_setting',
            array('setting_value' => $value),
            $db->quoteInto('setting_name = ?', $name)
        );

        if ($updated === 0) {
            $db->insert(
                'director_setting',
                array(
                    'setting_name'  => $name,
                    'setting_value' => $value,
                )
            );
        }

        if ($this->settings !== null) {
            $this->settings[$name] = $value;
        }

        return $this;
    }

    public function fetchSettings($force = true)
    {
        if ($force || $this->settings === null) {
            $db = $this->db();
            $query = $db->select()->from(
                array('s' => 'director_setting'),
                array('setting_name', 'setting_value')
            );
            $this->settings = (array) $db->fetchPairs($query);
        }

        return $this->settings;
    }

    public function getActivitylogNeighbors($id, $type = null, $name = null)
    {
        $db = $this->db();

        $greater = $db->select()->from(
            array('g' => 'director_activity_log'),
            array('id' => 'MIN(g.id)')
        )->where('id > ?', (int) $id);

        $smaller = $db->select()->from(
            array('l' => 'director_activity_log'),
            array('id' => 'MAX(l.id)')
        )->where('id < ?', (int) $id);

        if ($type !== null) {
            $greater->where('object_type = ?', $type);
            $smaller->where('object_type = ?', $type);
        }

        if ($name !== null) {
            $greater->where('object_name = ?', $name);
            $smaller->where('object_name = ?', $name);
        }

        $query = $db->select()->from(
            array('gt' => $greater),
            array(
                'prev' => 'lt.id',
                'next' => 'gt.id'
            )
        )->join(
            array('lt' => $smaller),
            '1 = 1',
            array()
        );

        return $db->fetchRow($query);
    }

    public function fetchActivityLogEntryById($id)
    {
        $sql = 'SELECT id, object_type, object_name, action_name,'
             . ' old_properties, new_properties, author, change_time,'
             . ' %s AS checksum, %s AS parent_checksum'
             . ' FROM director_activity_log WHERE id = %d';

        $sql = sprintf(
            $sql,
            $this->dbHexFunc('checksum'),
            $this->dbHexFunc('parent_checksum'),
            $id
        );

        return $this->db()->fetchRow($sql);
    }

    public function fetchActivityLogChecksumById($id, $binary = true)
    {
        $sql = sprintf(
            'SELECT %s AS checksum FROM director_activity_log WHERE id = %d',
            $this->dbHexFunc('checksum'),
            (int) $id
        );

        $result = $this->db()->fetchOne($sql);

        if ($binary) {
            return Util::hex2binary($result);
        } else {
            return $result;
        }
    }

    public function fetchActivityLogIdByChecksum($checksum)
    {
        $sql = 'SELECT id FROM director_activity_log WHERE checksum = ?';
        return $this->db()->fetchOne($sql, $this->quoteBinary($checksum));
    }

    public function fetchActivityLogEntry($checksum)
    {
        $sql = 'SELECT id, object_type, object_name, action_name,'
             . ' old_properties, new_properties, author, change_time,'
             . ' %s AS checksum, %s AS parent_checksum'
             . ' FROM director_activity_log WHERE checksum = ?';

        $sql = sprintf(
            $sql,
            $this->dbHexFunc('checksum'),
            $this->dbHexFunc('parent_checksum'));

        return $this->db()->fetchRow(
            $sql,
            $this->quoteBinary(Util::hex2binary($checksum))
        );
    }

    public function getLastActivityChecksum()
    {
        $select = "SELECT checksum FROM (SELECT * FROM (SELECT 1 AS pos, "
                . $this->dbHexFunc('checksum')
                . " AS checksum"
                . " FROM director_activity_log ORDER BY id DESC LIMIT 1) a"
                . " UNION SELECT 2 AS pos, '' AS checksum) u ORDER BY pos LIMIT 1";

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
            ->from('import_run', $this->dbHexFunc('rowset_checksum'))
            ->where('id = ?', $id);

        return $db->fetchOne($query);
    }

    protected function fetchTemplateRelations($type)
    {
        $db = $this->db();
        $query = $db->select()->from(
            array('p' => 'icinga_' . $type),
            array(
                'name'   => 'o.object_name',
                'parent' => 'p.object_name'
            )
        )->join(
            array('i' => 'icinga_' . $type . '_inheritance'),
            'p.id = i.parent_' . $type . '_id',
            array()
        )->join(
            array('o' => 'icinga_' . $type),
            'o.id = i.' . $type . '_id',
            array()
        )->where("o.object_type = 'template'")
         ->order('p.object_name')
         ->order('o.object_name');

        return $db->fetchAll($query);
    }

    public function fetchTemplateTree($type)
    {
        $relations = $this->fetchTemplateRelations($type);
        $children = array();
        $objects = array();
        foreach ($relations as $rel) {
            foreach (array('name', 'parent') as $col) {
                if (! array_key_exists($rel->$col, $objects)) {
                    $objects[$rel->$col] = (object) array(
                        'name'     => $rel->$col,
                        'children' => array()
                    );
                }
            }
        }

        foreach ($relations as $rel) {
            $objects[$rel->parent]->children[$rel->name] = $objects[$rel->name];
            $children[$rel->name] = $rel->parent;
        }

        foreach ($children as $name => $object) {
            unset($objects[$name]);
        }

        return $objects;
    }

    public function fetchLatestImportedRows($source, $desiredColumns = null)
    {
        $db = $this->db();
        if ($desiredColumns === null) {
            $columns = null;
        } else {
            $columns = array();
            foreach ($desiredColumns as $column) {
                if (false === ($pos = strpos($column, '.'))) {
                    $columns[$column] = $column;
                } else {
                    $column = substr($column, 0, $pos);
                    $columns[$column] = $column;
                }
            }
        }

        $lastRun = $db->select()->from(
            'import_run',
            array('checksum' => $this->dbHexFunc('rowset_checksum'))
        );

        if (is_int($source) || ctype_digit($source)) {
            $lastRun->where('source_id = ?', $source);
        } else {
            $lastRun->where('source_name = ?', $source);
        }

        $lastRun->order('start_time DESC')->limit(1);
        $checksum = $db->fetchOne($lastRun);

        return $this->fetchImportedRowsetRows($checksum, $columns);
    }

    public function fetchImportedRowsetRows($checksum, $columns)
    {
        $db = $this->db();
        $binchecksum = Util::hex2binary($checksum);

        $query = $db->select()->from(
            array('rsr' => 'imported_rowset_row'),
            array(
                'object_name'    => 'r.object_name',
                'property_name'  => 'p.property_name',
                'property_value' => 'p.property_value',
                'format'         => 'p.format'
            )
        )->join(
            array('r' => 'imported_row'),
            'rsr.row_checksum = r.checksum',
            array()
        )->join(
            array('rp' => 'imported_row_property'),
            'r.checksum = rp.row_checksum',
            array()
        )->join(
            array('p' => 'imported_property'),
            'p.checksum = rp.property_checksum',
            array()
        )->where('rsr.rowset_checksum = ?', $this->quoteBinary($binchecksum))->order('r.object_name');

        if ($columns === null) {
            $columns = $this->listImportedRowsetColumnNames($checksum);
        } else {
            $query->where('p.property_name IN (?)', $columns);
        }

        $result = array();
        $empty = (object) array();
        foreach ($columns as $k => $v) {
            $empty->$k = null;
        }

        foreach ($db->fetchAll($query) as $row) {
            if (! array_key_exists($row->object_name, $result)) {
                $result[$row->object_name] = clone($empty);
            }

            if ($row->format === 'json') {
                $result[$row->object_name]->{$row->property_name} = json_decode($row->property_value);
            } else {
                $result[$row->object_name]->{$row->property_name} = $row->property_value;
            }
        }

        return $result;
    }

    public function getLatestImportedChecksum($source)
    {
        if ($this->isPgsql()) {
            $col = "LOWER(ENCODE(rowset_checksum, 'hex'))";
        } else {
            $col = '(LOWER(HEX(import_run.rowset_checksum)))';
        }

        $db = $this->db();
        $lastRun = $db->select()->from('import_run', array($col));

        if (is_int($source) || ctype_digit($source)) {
            $lastRun->where('source_id = ?', (int) $source);
        } else {
            $lastRun->where('source_name = ?', $source);
        }

        $lastRun->order('start_time DESC')->limit(1);
        return $db->fetchOne($lastRun);
    }

    public function getObjectSummary()
    {
        $types = array(
            'host',
            'hostgroup',
            'service',
            'servicegroup',
            'user',
            'usergroup',
            'command',
            'timeperiod',
            'apiuser',
            'endpoint',
            'zone',
        );

        $queries = array();
        $db = $this->db();
        $cnt = "COALESCE(SUM(CASE WHEN o.object_type = '%s' THEN 1 ELSE 0 END), 0)";

        foreach ($types as $type) {
            $queries[] = $db->select()->from(
                array('o' => 'icinga_' . $type),
                array(
                    'icinga_type'  => "('" . $type . "')",
                    'cnt_object'   => sprintf($cnt, 'object'),
                    'cnt_template' => sprintf($cnt, 'template'),
                    'cnt_external' => sprintf($cnt, 'external_object'),
                    'cnt_total'    => 'COUNT(*)',
                )
            );
        }

        $query = $this->db()->select()->union($queries, Zend_Db_Select::SQL_UNION_ALL);

        $result = array();

        foreach ($db->fetchAll($query) as $row) {
            $result[$row->icinga_type] = $row;
        }

        return $result;
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
        )->where('rsr.rowset_checksum = ?', $this->quoteBinary(Util::hex2binary($checksum)));

        return $db->fetchCol($query);
    }

    public function enumCommands()
    {
        return $this->enumIcingaObjects('command');
    }

    public function enumCheckcommands()
    {
        $filters = array(
            'methods_execute IN (?)' => array('PluginCheck', 'IcingaCheck'),
            
        );
        return $this->enumIcingaObjects('command', $filters);
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

    public function enumZones()
    {
        return $this->enumIcingaObjects('zone');
    }

    public function enumNonglobalZones()
    {
        $filters = array('is_global = ?' => 'n');
        return $this->enumIcingaObjects('zone', $filters);
    }

    public function enumZoneTemplates()
    {
        return $this->enumIcingaTemplates('zone');
    }

    public function enumHosts()
    {
        return $this->enumIcingaObjects('host');
    }

    public function enumHostTemplates()
    {
        return $this->enumIcingaTemplates('host');
    }

    public function enumHostgroups()
    {
        return $this->enumIcingaObjects('hostgroup');
    }

    public function enumServices()
    {
        return $this->enumIcingaObjects('service');
    }

    public function enumServiceTemplates()
    {
        return $this->enumIcingaTemplates('service');
    }

    public function enumServicegroups()
    {
        return $this->enumIcingaObjects('servicegroup');
    }

    public function enumUsers()
    {
        return $this->enumIcingaObjects('user');
    }

    public function enumUserTemplates()
    {
        return $this->enumIcingaTemplates('user');
    }

    public function enumUsergroups()
    {
        return $this->enumIcingaObjects('usergroup');
    }

    public function enumApiUsers()
    {
        return $this->enumIcingaObjects('apiuser');
    }

    public function enumSyncRule()
    {
        return $this->enum('sync_rule', array('id', 'rule_name'));
    }

    public function enumImportSource()
    {
        return $this->enum('import_source', array('id', 'source_name'));
    }

    public function enumDatalist()
    {
        return $this->enum('director_datalist', array('id', 'list_name'));
    }

    public function enumDatafields()
    {
        return $this->enum('director_datafield', array(
            'id',
            "caption || ' (' || varname || ')'",
        ));
    }

    public function enum($table, $columns = null, $filters = array())
    {
        if ($columns === null) {
            $columns = array('id', 'object_name');
        }

        $select = $this->db()->select()->from($table, $columns)->order($columns[1]);
        foreach ($filters as $key => $val) {
            $select->where($key, $val);
        }

        return $this->db()->fetchPairs($select);
    }

    public function enumIcingaObjects($type, $filters = array())
    {
        $filters = array(
            'object_type IN (?)' => array('object', 'external_object')
        ) + $filters;

        return $this->enum('icinga_' . $type, null, $filters);
    }

    public function enumIcingaTemplates($type, $filters = array())
    {
        $filters = array('object_type = ?' => 'template') + $filters;
        return $this->enum('icinga_' . $type, null, $filters);
    }

    public function fetchDistinctHostVars()
    {
        $select = $this->db()->select()->distinct()->from(
            array('hv' => 'icinga_host_var'),
            array(
                'varname'  => 'hv.varname',
                'format'   => 'hv.format',
                'caption'  => 'df.caption',
                'datatype' => 'df.datatype'
            )
        )->joinLeft(
            array('df' => 'director_datafield'),
            'df.varname = hv.varname',
            array()
        )->order('varname');

        return $this->db()->fetchAll($select);
    }

    public function isPgsql()
    {
        return $this->getDbType() === 'pgsql';
    }

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
            return new Zend_Db_Expr("'\\x" . bin2hex($binary) . "'");
        }

        return $binary;
    }

    public function getUncollectedDeployments()
    {
        $db = $this->db();

        $query = $db->select()
            ->from('director_deployment_log')
            ->where('stage_name IS NOT NULL')
            ->where('stage_collected IS NULL')
            ->where('startup_succeeded IS NULL')
            ->order('stage_name');

        return DirectorDeploymentLog::loadAll($this, $query, 'stage_name');
    }
}
