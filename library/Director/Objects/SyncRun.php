<?php

namespace Icinga\Module\Director\Objects;

use Icinga\Module\Director\Data\Db\DbObject;

class SyncRun extends DbObject
{
    protected $table = 'sync_run';

    protected $keyName = 'id';

    protected $autoincKeyName = 'id';

    protected $defaultProperties = array(
        'id'                     => null,
        'rule_id'                => null,
        'rule_name'              => null,
        'start_time'             => null,
        'duration_ms'            => null,
        'objects_created'        => null,
        'objects_deleted'        => null,
        'objects_modified'       => null,
        'last_former_activity'   => null,
        'last_related_activity'  => null,
    );

    public static function start(SyncRule $rule)
    {
        return static::create(
            array(
                'start_time' => date('Y-m-d H:i:s'),
                'rule_id'    => $rule->id,
                'rule_name'  => $rule->rule_name,
            ),
            $rule->getConnection()
        );
    }

    public function countActivities()
    {
        return (int) $this->get('objects_deleted')
            + (int) $this->get('objects_created')
            + (int) $this->get('objects_modified');
    }
}
