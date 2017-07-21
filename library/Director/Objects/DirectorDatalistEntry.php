<?php

namespace Icinga\Module\Director\Objects;

use Icinga\Exception\IcingaException;
use Icinga\Exception\ProgrammingError;
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
        'allowed_roles' => null,
    );

    /**
     * @param $roles
     * @throws IcingaException
     * @codingStandardsIgnoreStart
     */
    public function setAllowed_roles($roles)
    {
        // @codingStandardsIgnoreEnd
        $key = 'allowed_roles';
        if (is_array($roles)) {
            $this->reallySet($key, json_encode($roles));
        } elseif (null === $roles) {
            $this->reallySet($key, null);
        } else {
            throw new ProgrammingError(
                'Expected array or null for allowed_roles, got %s',
                var_export($roles, 1)
            );
        }
    }

    /**
     * @return array|null
     * @codingStandardsIgnoreStart
     */
    public function getAllowed_roles()
    {
        // @codingStandardsIgnoreEnd
        $roles = $this->getProperty('allowed_roles');
        if (is_string($roles)) {
            return json_decode($roles);
        } else {
            return $roles;
        }
    }

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
