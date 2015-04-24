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
