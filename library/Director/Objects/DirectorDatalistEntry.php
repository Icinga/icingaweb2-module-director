<?php

namespace Icinga\Module\Director\Objects;

use Icinga\Module\Director\Data\Db\DbObject;
use RuntimeException;

class DirectorDatalistEntry extends DbObject
{
    protected $keyName = ['list_id', 'entry_name'];

    protected $table = 'director_datalist_entry';

    private $shouldBeRemoved = false;

    protected $defaultProperties = [
        'list_id'       => null,
        'entry_name'    => null,
        'entry_value'   => null,
        'format'        => null,
        'allowed_roles' => null,
    ];

    /**
     * @param DirectorDatalist $list
     * @return static[]
     */
    public static function loadAllForList(DirectorDatalist $list)
    {
        $query = $list->getDb()
            ->select()
            ->from('director_datalist_entry')
            ->where('list_id = ?', $list->get('id'))
            ->order('entry_name ASC');

        return static::loadAll($list->getConnection(), $query, 'entry_name');
    }

    /**
     * @param $roles
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
            throw new RuntimeException(
                'Expected array or null for allowed_roles, got %s',
                var_export($roles, true)
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
        $this->set('entry_value', $object->get('entry_value'));
        if ($object->get('format')) {
            $this->set('format', $object->get('format'));
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
