<?php

namespace Icinga\Module\Director\Tables;

use Icinga\Module\Director\Tables\IcingaNotificationTable;

class IcingaNotificationTemplateTable extends IcingaNotificationTable
{
    public function getBaseQuery()
    {
        return $this->getUnfilteredQuery()->where('n.object_type = ?', 'template');
    }
}
