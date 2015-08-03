<?php

namespace Icinga\Module\Director\Tables;

use Icinga\Module\Director\Tables\IcingaCommandTable;

class IcingaCommandTemplateTable extends IcingaCommandTable
{
    public function getBaseQuery()
    {
        return $this->getUnfilteredQuery()->where('c.object_type = ?', 'template');
    }
}
