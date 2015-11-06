<?php

namespace Icinga\Module\Director\Objects;

use Icinga\Module\Director\Data\Db\DbObject;

class SyncProperty extends DbObject
{
    protected $table = 'sync_property';

    protected $keyName = 'id';

    protected $autoincKeyName = 'id';

    protected $defaultProperties = array(
        'id'             	=> null,
        'rule_id'        	=> null,
        'source_id' 		=> null,
        'source_expression' => null,
	    'destination_field'	=> null,
	    'priority'		    => null,
	    'filter_expression'	=> null,
    	'merge_policy'		=> null
    );

    public function setSource_column($value)
    {
        $this->source_expression = '${' . $value . '}';
        return $this; 
    }

    public function sourceIsSingleColumn()
    {
        return $this->getSourceColumn() !== null;
    }

    public function getSourceColumn()
    {
        if (preg_match('/^\${([A-Za-z0-9_-]+)}$/', $this->source_expression, $m)) {
            return $m[1];
        }

        return null;
    }
}
