<?php

namespace Icinga\Module\Director\Objects;

use Icinga\Module\Director\Data\Db\DbObject;

class IcingaNotificationField extends DbObject
{
    protected $keyName = array('notification_id', 'datafield_id');

    protected $table = 'icinga_notification_field';

    protected $defaultProperties = array(
        'notification_id' => null,
        'datafield_id'    => null,
        'is_required'     => null
    );
}
