<?php

namespace Icinga\Module\Director\Tables;

use Icinga\Module\Director\Tables\IcingaHostTable;

class IcingaHostTemplateTable extends IcingaHostTable
{
    public function getBaseQuery()
    {
        return $this->getUnfilteredQuery()->where('h.object_type = ?', 'template');
    }
}
