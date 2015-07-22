<?php

namespace Icinga\Module\Director\Objects;

use Icinga\Module\Director\Data\Db\DbObject;

class DirectorDatalistEntry extends DbObject
{
    protected $keyName = array('list_id', 'entry_name');

    protected $table = 'director_datalist_entry';

    protected $defaultProperties = array(
        'list_id'       => null,
        'entry_name'    => null,
        'entry_value'   => null,
        'format'        => null,
    );

    public function onInsert()
    {
    }

    public function onUpdate()
    {
    }

    public function onDelete()
    {
    }
}
