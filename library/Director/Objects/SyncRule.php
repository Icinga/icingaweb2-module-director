<?php

namespace Icinga\Module\Director\Objects;

use Icinga\Module\Director\Data\Db\DbObject;

class SyncRule extends DbObject
{
    protected $table = 'sync_rule';

    protected $keyName = 'id';

    protected $autoincKeyName = 'id';

    protected $defaultProperties = array(
        'id'             	=> null,
        'rule_name'        	=> null,
        'object_type' 		=> null,
        'update_policy'     => null,
	    'purge_existing'	=> null,
	    'filter_expression'	=> null,
    );

    public function listInvolvedSourceIds()
    {
        if (! $this->hasBeenLoadedFromDb()) {
            return array();
        }

        $db = $this->getDb();
        return array_map('intval', array_unique(
            $db->fetchCol(
                $db->select()
                   ->from(array('p' => 'sync_property'), 'p.source_id')
                   ->join(array('s' => 'import_source'), 's.id = p.source_id', array())
                   ->where('rule_id = ?', $this->id)
                   ->order('s.source_name')
            )
        ));
    }

    public function getPriorityForNextProperty()
    {
        if (! $this->hasBeenLoadedFromDb()) {
            return 1;
        }
        
        $db = $this->getDb();
        return $db->fetchOne(
            $db->select()
                ->from(
                    'sync_property',
                    array('priority' => '(CASE WHEN MAX(priority) IS NULL THEN 1 ELSE MAX(priority) + 1 END)')
                )->where('rule_id = ?', $this->id)
        );
    }

    public function fetchSyncProperties()
    {
        $db = $this->getDb();
        return SyncProperty::loadAll(
            $this->getConnection(),
            $db->select()->from('sync_property')->where('rule_id = ?', $this->id)
        );

        return $this->syncProperties;
    }
}
