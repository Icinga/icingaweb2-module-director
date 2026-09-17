<?php

namespace Icinga\Module\Director\Objects;

use Countable;
use Exception;
use Icinga\Exception\NotFoundError;
use Icinga\Module\Director\Db\Cache\PrefetchCache;
use Icinga\Module\Director\IcingaConfig\IcingaConfigRenderer;
use Icinga\Module\Director\IcingaConfig\IcingaConfigHelper as c;
use Icinga\Module\Director\IcingaConfig\IcingaLegacyConfigHelper as c1;
use InvalidArgumentException;
use Iterator;
use RuntimeException;

class IcingaObjectGroups implements Iterator, Countable, IcingaConfigRenderer
{
    /** Assign group membership, rendered as "groups = [ ... ]" */
    const OP_ASSIGN = '=';

    /** Add to inherited group membership, rendered as "groups += [ ... ]" */
    const OP_ADD = '+=';

    /** Remove from inherited group membership, rendered as "groups -= [ ... ]" */
    const OP_REMOVE = '-=';

    /** All valid operators, in the order they have to be rendered */
    const OPERATORS = array('=', '+=', '-=');

    protected $storedGroups = array();

    protected $groups = array();

    /** @var array group name => operator */
    protected $operators = array();

    /** @var array group name => operator, as currently stored in the DB */
    protected $storedOperators = array();

    protected $modified = false;

    protected $object;

    private $position = 0;

    protected $idx = array();

    public function __construct(IcingaObject $object)
    {
        $this->object = $object;

        if (! $object->hasBeenLoadedFromDb() && PrefetchCache::shouldBeUsed()) {
            /** @var IcingaObjectGroup $class */
            $class = $this->getGroupClass();
            $class::prefetchAll($this->object->getConnection());
        }
    }

    /**
     * @param string $operator
     * @return string
     */
    protected function assertValidOperator($operator)
    {
        if (! in_array($operator, self::OPERATORS, true)) {
            throw new InvalidArgumentException(sprintf(
                'Invalid group membership operator: %s',
                var_export($operator, true)
            ));
        }

        return $operator;
    }

    /**
     * The operator for a single group membership, defaults to '='
     *
     * @param string $name
     * @return string
     */
    public function getOperator($name)
    {
        if (array_key_exists($name, $this->operators)) {
            return $this->operators[$name];
        }

        return self::OP_ASSIGN;
    }

    /**
     * Group names using exactly the given operator
     *
     * @param string $operator
     * @return array
     */
    public function listGroupNamesForOperator($operator)
    {
        $this->assertValidOperator($operator);
        $names = array();
        foreach (array_keys($this->groups) as $name) {
            if ($this->getOperator($name) === $operator) {
                $names[] = $name;
            }
        }

        return $names;
    }

    /**
     * Group names using any of the given operators
     *
     * @param array $operators
     * @return array
     */
    public function listGroupNamesForOperators(array $operators)
    {
        $names = array();
        foreach ($operators as $operator) {
            $names = array_merge($names, $this->listGroupNamesForOperator($operator));
        }
        sort($names);

        return $names;
    }

    /**
     * Groups this object ends up with, ignoring inheritance: '=' and '+=' minus '-='
     *
     * @return array
     */
    public function listEffectiveGroupNames()
    {
        return array_values(array_diff(
            $this->listGroupNamesForOperators(array(self::OP_ASSIGN, self::OP_ADD)),
            $this->listGroupNamesForOperator(self::OP_REMOVE)
        ));
    }

    /**
     * Stored (unmodified) group names using exactly the given operator
     *
     * @param string $operator
     * @return array
     */
    public function listOriginalGroupNamesForOperator($operator)
    {
        $this->assertValidOperator($operator);
        $names = array();
        foreach (array_keys($this->storedGroups) as $name) {
            $stored = array_key_exists($name, $this->storedOperators)
                ? $this->storedOperators[$name]
                : self::OP_ASSIGN;
            if ($stored === $operator) {
                $names[] = $name;
            }
        }

        return $names;
    }

    #[\ReturnTypeWillChange]
    public function count()
    {
        return count($this->groups);
    }

    #[\ReturnTypeWillChange]
    public function rewind()
    {
        $this->position = 0;
    }

    public function hasBeenModified()
    {
        return $this->modified;
    }

    #[\ReturnTypeWillChange]
    public function current()
    {
        if (! $this->valid()) {
            return null;
        }

        return $this->groups[$this->idx[$this->position]];
    }

    #[\ReturnTypeWillChange]
    public function key()
    {
        return $this->idx[$this->position];
    }

    #[\ReturnTypeWillChange]
    public function next()
    {
        ++$this->position;
    }

    #[\ReturnTypeWillChange]
    public function valid()
    {
        return array_key_exists($this->position, $this->idx);
    }

    public function get($key)
    {
        if (array_key_exists($key, $this->groups)) {
            return $this->groups[$key];
        }

        return null;
    }

    /**
     * Replace all memberships using the given operator
     *
     * Memberships with a different operator are left untouched, this is what
     * allows the three group form fields (assign, add, remove) to coexist.
     *
     * @param $group
     * @param string $operator
     * @return $this
     * @throws NotFoundError
     */
    public function set($group, $operator = self::OP_ASSIGN)
    {
        $this->assertValidOperator($operator);

        if (! is_array($group)) {
            $group = $group === null ? array() : array($group);
        }

        $class = $this->getGroupClass();
        $new = array();

        foreach ($group as $g) {
            if ($g instanceof $class) {
                $new[] = $g->object_name;
            } elseif (! empty($g)) {
                $new[] = $g;
            }
        }

        // Compare against this operator only, otherwise an unrelated field
        // would be able to wipe our memberships
        $existing = $this->listGroupNamesForOperator($operator);
        $compare = $new;
        sort($existing);
        sort($compare);
        if ($existing === $compare) {
            return $this;
        }

        foreach ($this->listGroupNamesForOperator($operator) as $name) {
            unset($this->groups[$name]);
            unset($this->operators[$name]);
        }

        $this->modified = true;
        $this->refreshIndex();

        if (empty($new)) {
            return $this;
        }

        return $this->add($new, 'fail', $operator);
    }

    /**
     * @param string $operator
     * @param $group
     * @return $this
     * @throws NotFoundError
     */
    public function setForOperator($operator, $group)
    {
        return $this->set($group, $operator);
    }

    /**
     * Magic isset check
     *
     * @return boolean
     */
    public function __isset($group)
    {
        return array_key_exists($group, $this->groups);
    }

    public function remove($group)
    {
        if (array_key_exists($group, $this->groups)) {
            unset($this->groups[$group]);
            unset($this->operators[$group]);
        }

        $this->modified = true;
        $this->refreshIndex();
    }

    protected function refreshIndex()
    {
        ksort($this->groups);
        ksort($this->operators);
        $this->idx = array_keys($this->groups);
    }

    /**
     * @param $group
     * @param string $onError
     * @param string $operator
     * @return $this
     * @throws NotFoundError
     * @throws \Icinga\Module\Director\Exception\DuplicateKeyException
     */
    public function add($group, $onError = 'fail', $operator = self::OP_ASSIGN)
    {
        $this->assertValidOperator($operator);

        // TODO: only one query when adding array
        if (is_array($group)) {
            foreach ($group as $g) {
                $this->add($g, $onError, $operator);
            }
            return $this;
        }

        if (is_int($group)) {
            $group = (string) $group;
        }

        /** @var IcingaObjectGroup $class */
        $class = $this->getGroupClass();

        $name = $group instanceof $class ? $group->getObjectName() : $group;

        // Already a member? Then this might still be an operator change
        if (is_string($name) && array_key_exists($name, $this->groups)) {
            if ($this->getOperator($name) !== $operator) {
                $this->operators[$name] = $operator;
                $this->modified = true;
            }

            return $this;
        }

        if ($group instanceof $class) {
            $this->groups[$group->object_name] = $group;
        } elseif (is_string($group)) {
            $connection = $this->object->getConnection();

            try {
                $this->groups[$group] = $class::load($group, $connection);
            } catch (NotFoundError $e) {
                switch ($onError) {
                    case 'autocreate':
                        $newGroup = $class::create(array(
                            'object_type' => 'object',
                            'object_name' => $group
                        ));
                        $newGroup->store($connection);
                        $this->groups[$group] = $newGroup;
                        break;
                    case 'fail':
                        throw new NotFoundError(
                            'The group "%s" doesn\'t exist.',
                            $group
                        );
                        break;
                    case 'ignore':
                        return $this;
                }
            }
        } else {
            throw new RuntimeException(
                'Invalid group object: %s',
                var_export($group, true)
            );
        }

        $this->operators[$name] = $operator;
        $this->modified = true;
        $this->refreshIndex();

        return $this;
    }

    protected function getGroupTableName()
    {
        return $this->object->getTableName() . 'group';
    }


    protected function getGroupMemberTableName()
    {
        return $this->object->getTableName() . 'group_' . $this->getType();
    }

    public function listGroupNames()
    {
        return array_keys($this->groups);
    }

    public function listOriginalGroupNames()
    {
        return array_keys($this->storedGroups);
    }

    public function getType()
    {
        return $this->object->getShortTableName();
    }

    protected function loadFromDb()
    {
        $db = $this->object->getDb();
        $connection = $this->object->getConnection();

        $type = $this->getType();

        $table = $this->object->getTableName();
        $query = $db->select()->from(
            array('go' => $table . 'group_' . $type),
            array()
        )->join(
            array('g' => $table . 'group'),
            'go.' . $type . 'group_id = g.id',
            '*'
        )->where('go.' . $type . '_id = ?', $this->object->id)
        ->order('g.object_name');

        $class = $this->getGroupClass();
        $this->groups = $class::loadAll($connection, $query, 'object_name');

        // Operators cannot ride along with the query above, loadAll() would
        // choke on a column the group object doesn't know about
        $operatorQuery = $db->select()->from(
            array('go' => $table . 'group_' . $type),
            array(
                'group_name' => 'g.object_name',
                'operator'   => 'go.operator',
            )
        )->join(
            array('g' => $table . 'group'),
            'go.' . $type . 'group_id = g.id',
            array()
        )->where('go.' . $type . '_id = ?', $this->object->id);

        $this->operators = $db->fetchPairs($operatorQuery);
        $this->setBeingLoadedFromDb();

        return $this;
    }

    public function store()
    {
        $storedGroups = array_keys($this->storedGroups);
        $groups = array_keys($this->groups);

        $objectId = $this->object->id;
        $type = $this->getType();

        $objectCol = $type . '_id';
        $groupCol = $type . 'group_id';

        $toDelete = array_diff($storedGroups, $groups);
        foreach ($toDelete as $group) {
            $where = sprintf(
                $objectCol . ' = %d AND ' . $groupCol . ' = %d',
                $objectId,
                $this->storedGroups[$group]->id
            );

            $this->object->db->delete(
                $this->getGroupMemberTableName(),
                $where
            );
        }

        $toAdd = array_diff($groups, $storedGroups);
        foreach ($toAdd as $group) {
            $this->object->db->insert(
                $this->getGroupMemberTableName(),
                array(
                    $objectCol => $objectId,
                    $groupCol => $this->groups[$group]->id,
                    'operator' => $this->getOperator($group)
                )
            );
        }

        // A membership that stayed, but changed its operator
        $toUpdate = array_intersect($groups, $storedGroups);
        foreach ($toUpdate as $group) {
            $stored = array_key_exists($group, $this->storedOperators)
                ? $this->storedOperators[$group]
                : self::OP_ASSIGN;
            $current = $this->getOperator($group);
            if ($stored === $current) {
                continue;
            }

            $this->object->db->update(
                $this->getGroupMemberTableName(),
                array('operator' => $current),
                sprintf(
                    $objectCol . ' = %d AND ' . $groupCol . ' = %d',
                    $objectId,
                    $this->groups[$group]->id
                )
            );
        }

        $this->setBeingLoadedFromDb();

        return true;
    }

    public function setBeingLoadedFromDb()
    {
        $this->storedGroups = array();
        foreach ($this->groups as $k => $v) {
            $this->storedGroups[$k] = clone($v);
            $this->storedGroups[$k]->id = $v->id;
        }

        $this->storedOperators = $this->operators;

        // loadForStoredObject() doesn't call this when prefetching, so the
        // index would otherwise stay empty and the Iterator yield nothing
        $this->refreshIndex();

        $this->modified = false;
    }

    protected function getGroupClass()
    {
        return __NAMESPACE__ . '\\Icinga' . ucfirst($this->object->getShortTableName()) . 'Group';
    }

    public static function loadForStoredObject(IcingaObject $object)
    {
        $groups = new static($object);

        if (PrefetchCache::shouldBeUsed()) {
            $cache = PrefetchCache::instance();
            $groups->groups = $cache->groups($object);
            $groups->operators = $cache->groupOperators($object);
            $groups->setBeingLoadedFromDb();
        } else {
            $groups->loadFromDb();
        }

        return $groups;
    }

    public function toConfigString()
    {
        $config = '';

        // '=' has to be rendered before '+=' and '-=', hence the fixed order
        foreach (self::OPERATORS as $operator) {
            $groups = $this->listGroupNamesForOperator($operator);
            if (empty($groups)) {
                continue;
            }

            $config .= c::renderKeyOperatorValue(
                'groups',
                $operator,
                c::renderArray($groups)
            );
        }

        return $config;
    }

    public function toLegacyConfigString($additionalGroups = array())
    {
        // Icinga 1.x knows no += / -=, so we render what the object ends up with
        $groups = array_merge(
            $this->listGroupNamesForOperators(array(self::OP_ASSIGN, self::OP_ADD)),
            $additionalGroups
        );
        $groups = array_values(array_diff(
            array_unique($groups),
            $this->listGroupNamesForOperator(self::OP_REMOVE)
        ));

        if (empty($groups)) {
            return '';
        }

        $type = $this->object->getLegacyObjectType();

        return c1::renderKeyValue($type . 'groups', c1::renderArray($groups));
    }

    public function __toString()
    {
        try {
            return $this->toConfigString();
        } catch (Exception $e) {
            trigger_error($e);
            $previousHandler = set_exception_handler(
                function () {
                }
            );
            restore_error_handler();
            if ($previousHandler !== null) {
                call_user_func($previousHandler, $e);
                die();
            } else {
                die($e->getMessage());
            }
        }
    }

    public function __destruct()
    {
        unset($this->storedGroups);
        unset($this->groups);
        unset($this->operators);
        unset($this->storedOperators);
        unset($this->object);
    }
}
