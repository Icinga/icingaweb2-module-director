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
