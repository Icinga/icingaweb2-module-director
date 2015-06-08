<?php

namespace Icinga\Module\Director\Objects;

use Icinga\Module\Director\Data\Db\DbObject;

abstract class IcingaObject extends DbObject
{
    protected $keyName = 'id';

    protected $autoincKeyName = 'id';

    protected $supportsCustomVars = false;

    public function supportsCustomVars()
    {
        return $this->supportsCustomVars;
    }

    public function isTemplate()
    {
        return property_exists($this, 'object_type')
            && $this->object_type === 'template';
    }

    public function isApplyRule()
    {
        return property_exists($this, 'object_type')
            && $this->object_type === 'apply';
    }

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
