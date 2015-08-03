<?php

namespace Icinga\Module\Director\Tables;

use Icinga\Module\Director\Tables\IcingaServiceTable;

class IcingaServiceTemplateTable extends IcingaServiceTable
{
    public function getBaseQuery()
    {
        return $this->getUnfilteredQuery()->where('s.object_type = ?', 'template');
    }
}
