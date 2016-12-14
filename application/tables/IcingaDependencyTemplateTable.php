<?php

namespace Icinga\Module\Director\Tables;

use Icinga\Module\Director\Tables\IcingaDependencyTable;

class IcingaDependencyTemplateTable extends IcingaDependencyTable
{
    public function getBaseQuery()
    {
        return $this->getUnfilteredQuery()->where('d.object_type = ?', 'template');
    }
}
