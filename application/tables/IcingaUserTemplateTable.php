<?php

namespace Icinga\Module\Director\Tables;

use Icinga\Module\Director\Tables\IcingaUserTable;

class IcingaUserTemplateTable extends IcingaUserTable
{
    public function getBaseQuery()
    {
        return $this->getUnfilteredQuery()->where('u.object_type = ?', 'template');
    }
}
