<?php

namespace Icinga\Module\Director\Objects;

use Icinga\Module\Director\Data\Db\DbObject;

abstract class IcingaObject extends DbObject
{
    protected $keyName = 'id';

    protected $autoincKeyName = 'id';

    public function onInsert()
    {
        DirectorActivityLog::logCreation($this, $this->connection);
    }

    public function onUpdate()
    {
        DirectorActivityLog::logModification($this, $this->connection);
    }

    public function onDelete()
    {
        DirectorActivityLog::logRemoval($this, $this->connection);
    }
}
