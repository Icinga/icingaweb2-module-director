<?php

namespace Icinga\Module\Director\Objects;

use Icinga\Module\Director\Data\Db\DbObject;

class DirectorDatalistEntry extends DbObject
{
    protected $keyName = array('list_id', 'entry_name');

    protected $table = 'director_datalist_entry';

    private $shouldBeRemoved = false;

    protected $defaultProperties = array(
        'list_id'       => null,
        'entry_name'    => null,
        'entry_value'   => null,
        'format'        => null,
    );

    public function replaceWith(DirectorDatalistEntry $object)
    {
        $this->entry_value = $object->entry_value;
        if ($object->format) {
            $this->format = $object->format;
        }

        return $this;
    }

    public function merge(DirectorDatalistEntry $object)
    {
        return $this->replaceWith($object);
    }

    public function markForRemoval($remove = true)
    {
        $this->shouldBeRemoved = $remove;
        return $this;
    }

    public function shouldBeRemoved()
    {
        return $this->shouldBeRemoved;
    }

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
