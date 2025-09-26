<?php

namespace Icinga\Module\Director\CustomVariable;

use Icinga\Module\Director\Db;
use Icinga\Module\Director\IcingaConfig\IcingaConfigHelper as c;
use Icinga\Module\Director\IcingaConfig\IcingaLegacyConfigHelper as c1;
use Icinga\Module\Director\IcingaConfig\IcingaConfigRenderer;
use Icinga\Module\Director\Objects\IcingaObject;
use Countable;
use Exception;
use Iterator;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

class CustomVariables implements Iterator, Countable, IcingaConfigRenderer
{
    /** @var CustomVariable[] */
    protected $storedVars = array();

    /** @var CustomVariable[]  */
    protected $vars = array();

    protected $modified = false;

    private $position = 0;

    private $overrideKeyName;

    protected $idx = array();

    private $whiteList = [];

    protected static $allTables = array(
        'icinga_command_var',
        'icinga_host_var',
        'icinga_notification_var',
        'icinga_service_set_var',
        'icinga_service_var',
        'icinga_user_var',
    );

    public static function countAll($varname, Db $connection)
    {
        $db = $connection->getDbAdapter();
        $parts = array();
        $where = $db->quoteInto('varname = ?', $varname);
        foreach (static::$allTables as $table) {
            $parts[] = "SELECT COUNT(*) as cnt FROM $table WHERE $where";
        }

        $sub = implode(' UNION ALL ', $parts);
        $query = "SELECT SUM(sub.cnt) AS cnt FROM ($sub) sub";

        return (int) $db->fetchOne($query);
    }

    public static function deleteAll($varname, Db $connection)
    {
        $db = $connection->getDbAdapter();
        $where = $db->quoteInto('varname = ?', $varname);
        foreach (static::$allTables as $table) {
            $db->delete($table, $where);
        }
    }

    public static function renameAll($oldname, $newname, Db $connection)
    {
        $db = $connection->getDbAdapter();
        $where = $db->quoteInto('varname = ?', $oldname);
        foreach (static::$allTables as $table) {
            $db->update($table, ['varname' => $newname], $where);
        }
    }

    #[\ReturnTypeWillChange]
    public function count()
    {
        $count = 0;
        foreach ($this->vars as $var) {
            if (! $var->hasBeenDeleted()) {
                $count++;
            }
        }

        return $count;
    }

    #[\ReturnTypeWillChange]
    public function rewind()
    {
        $this->position = 0;
    }

    #[\ReturnTypeWillChange]
    public function current()
    {
        if (! $this->valid()) {
            return null;
        }

        return $this->vars[$this->idx[$this->position]];
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

    /**
     * Generic setter
     *
     * @param string $key
     * @param mixed  $value
     *
     * @return self
     */
    public function set($key, $value)
    {
        $key = (string) $key;

        if ($value instanceof CustomVariable) {
            $value = clone($value);
        } else {
            if ($value === null) {
                $this->__unset($key);
                return $this;
            }
            $value = CustomVariable::create($key, $value);
        }

        // Hint: isset($this->$key) wouldn't conflict with protected properties
        if ($this->__isset($key)) {
            if ($value->equals($this->get($key))) {
                return $this;
            } else {
                if (get_class($this->vars[$key]) === get_class($value)) {
                    $this->vars[$key]->setValue($value->getValue())->setModified();
                } else {
                    $this->vars[$key] = $value->setLoadedFromDb()->setModified();
                }
            }
        } else {
            $this->vars[$key] = $value->setModified();
        }

        $this->modified = true;
        $this->refreshIndex();

        return $this;
    }

    public function setWhiteList(array $whitelist): self
    {
        $this->whiteList = $whitelist;

        return $this;
    }

    public function getWhiteList(array $whitelist): array
    {
        return $this->whiteList;
    }

    protected function refreshIndex()
    {
        $this->idx = array();
        ksort($this->vars);
        foreach ($this->vars as $name => $var) {
            if (! $var->hasBeenDeleted()) {
                $this->idx[] = $name;
            }
        }
    }

    public static function loadForStoredObject(IcingaObject $object)
    {
        $db    = $object->getDb();

        $type = $object->getShortTableName();
        $query = $db->select()->from(
            array('v' => $object->getVarsTableName()),
            array(
                'v.' . $type . '_id',
                'v.varname',
                'v.varvalue',
                'v.format',
            )
        )->where(sprintf('v.%s = ?', $object->getVarsIdColumn()), $object->get('id'));

        $vars = new CustomVariables();
        foreach ($db->fetchAll($query) as $row) {
            $vars->vars[$row->varname] = CustomVariable::fromDbRow($row);
        }
        $vars->refreshIndex();
        $vars->setBeingLoadedFromDb();
        return $vars;
    }

    public static function forStoredRows($rows)
    {
        $vars = new CustomVariables();
        foreach ($rows as $row) {
            $vars->vars[$row->varname] = CustomVariable::fromDbRow($row);
        }
        $vars->refreshIndex();
        $vars->setBeingLoadedFromDb();

        return $vars;
    }

    public function storeToDb(IcingaObject $object)
    {
        $db            = $object->getDb();
        $table         = $object->getVarsTableName();
        $foreignColumn = $object->getVarsIdColumn();
        $foreignId     = $object->get('id');


        foreach ($this->vars as $var) {
            $uuid = $var->getUuid()?->getBytes();
            if ($var->isNew()) {
                $row = [
                    $foreignColumn  => $foreignId,
                    'varname'       => $var->getKey(),
                    'varvalue'      => $var->getDbValue(),
                    'format'        => $var->getDbFormat()
                ];

                if ($object->getShortTableName() === 'host' && $uuid) {
                    $row['property_uuid'] = $uuid;
                }

                $db->insert($table, $row);
                $var->setLoadedFromDb();

                continue;
            }

            $where = $db->quoteInto(sprintf('%s = ?', $foreignColumn), (int) $foreignId)
                   . $db->quoteInto(' AND varname = ?', $var->getKey());

            if ($var->hasBeenDeleted()) {
                $db->delete($table, $where);
            } elseif ($var->hasBeenModified()) {
                $data = [
                    'varvalue' => $var->getDbValue(),
                    'format'   => $var->getDbFormat()
                ];

                if ($object->getShortTableName() === 'host' && $uuid) {
                    $data['property_uuid'] = $uuid;
                }

                $db->update(
                    $table,
                    $data,
                    $where
                );
            }
        }

        $this->setBeingLoadedFromDb();
    }

    public function get($key)
    {
        if (array_key_exists($key, $this->vars)) {
            return $this->vars[$key];
        }

        return null;
    }

    public function hasBeenModified()
    {
        if ($this->modified) {
            return true;
        }

        foreach ($this->vars as $var) {
            if ($var->hasBeenModified()) {
                return true;
            }
        }

        return false;
    }

    public function setBeingLoadedFromDb()
    {
        $this->modified = false;
        $this->storedVars = array();
        foreach ($this->vars as $key => $var) {
            $this->storedVars[$key] = clone($var);
            $var->setUnmodified();
            $var->setLoadedFromDb();
        }

        return $this;
    }

    public function restoreStoredVar($key)
    {
        if (array_key_exists($key, $this->storedVars)) {
            $this->vars[$key] = clone($this->storedVars[$key]);
            $this->vars[$key]->setUnmodified();
            $this->recheckForModifications();
            $this->refreshIndex();
        } elseif (array_key_exists($key, $this->vars)) {
            unset($this->vars[$key]);
            $this->recheckForModifications();
            $this->refreshIndex();
        }
    }

    protected function recheckForModifications()
    {
        $this->modified = false;
        foreach ($this->vars as $var) {
            if ($var->hasBeenModified()) {
                $this->modified = true;

                return;
            }
        }
    }

    public function getOriginalVars()
    {
        return $this->storedVars;
    }

    public function flatten()
    {
        $flat = array();
        foreach ($this->vars as $key => $var) {
            $var->flatten($flat, $key);
        }

        return $flat;
    }

    public function checksum()
    {
        $sums = array();
        foreach ($this->vars as $key => $var) {
            $sums[] = $key . '=' . $var->checksum();
        }

        return sha1(implode('|', $sums), true);
    }

    public function setOverrideKeyName($name)
    {
        $this->overrideKeyName = $name;
        return $this;
    }

    public function toConfigString(?IcingaObject $object = null, $renderExpressions = false)
    {
        $out = '';

        foreach ($this as $key => $var) {
            // TODO: ctype_alnum + underscore?
            $out .= $this->renderSingleVar($key, $var, $object, $renderExpressions);
        }

        return $out;
    }

    public function toLegacyConfigString()
    {
        $out = '';

        ksort($this->vars);
        foreach ($this->vars as $key => $var) {
            // TODO: ctype_alnum + underscore?
            // vars with ARGn will be handled by IcingaObject::renderLegacyCheck_command
            if (substr($key, 0, 3) == 'ARG') {
                continue;
            }

            switch ($type = $var->getType()) {
                case 'String':
                case 'Number':
                    # TODO: Make Prefetchable
                    $out .= c1::renderKeyValue(
                        '_' . $key,
                        $var->toLegacyConfigString()
                    );
                    break;
                default:
                    $out .= c1::renderKeyValue(
                        '# _' . $key,
                        sprintf('(unsupported: %s)', $type)
                    );
            }
        }

        if ($out !== '') {
            $out = "\n" . $out;
        }

        return $out;
    }

    /**
     * @param string $key
     * @param CustomVariable $var
     * @param bool $renderExpressions
     *
     * @return string
     */
    protected function renderSingleVar($key, $var, ?IcingaObject $object = null, $renderExpressions = false)
    {
        if ($var instanceof CustomVariableString) {
            $var->setWhiteList($this->whiteList);
        }

        if ($key === $this->overrideKeyName) {
            return c::renderKeyOperatorValue(
                $this->renderKeyName($key),
                '+=',
                $var->toConfigStringPrefetchable($renderExpressions)
            );
        } elseif ($var instanceof CustomVariableDictionary) {
            if ($object === null || ($object->getShortTableName() !== 'host')) {
                return c::renderKeyValue(
                    $this->renderKeyName($key),
                    $var->toConfigStringPrefetchable($renderExpressions)
                );
            } elseif ($object->getShortTableName() === 'host') {
                $type = $object->getShortTableName();
                $objectId = $object->get('id');
                $ids =  $object->listAncestorIds() + [$object->get('id')];
                $query = $object->getDb()->select()->from(
                    ['dp' => 'director_property'],
                    ['value_type']
                )
                    ->join(['iop' => 'icinga_' . $type . '_property'], 'dp.uuid = iop.property_uuid', [])
                    ->join(['io' => 'icinga_' . $type], 'iop.' . $type . '_uuid = io.uuid', ['object_id' => 'io.id'])
                    ->join(['iov' => 'icinga_' . $type . '_var'], 'iov.' . $type . '_id = io.id', [])
                    ->where('dp.key_name = ?', $var->getKey())
                    ->where('io.id IN (?)', $ids);

                $row = (array) $object->getDb()->fetchRow($query);
                if (isset($row['value_type']) && $row['value_type'] === 'dynamic-dictionary' && $objectId !== $row['object_id']) {
                    return c::renderKeyOperatorValue(
                        $this->renderKeyName($key),
                        '+=',
                        $var->toConfigStringPrefetchable($renderExpressions)
                    );
                } else {
                    return c::renderKeyValue(
                        $this->renderKeyName($key),
                        $var->toConfigStringPrefetchable($renderExpressions)
                    );
                }
            }
        } else {
            return c::renderKeyValue(
                $this->renderKeyName($key),
                $var->toConfigStringPrefetchable($renderExpressions)
            );
        }
    }

    protected function renderKeyName($key)
    {
        return 'vars' . self::renderKeySuffix($key);
    }

    public static function renderKeySuffix($key)
    {
        if (preg_match('/^[a-z][a-z0-9_]*$/i', $key)) {
            return '.' . c::escapeIfReserved($key);
        } else {
            return '[' . c::renderString($key) . ']';
        }
    }

    public function __get($key)
    {
        return $this->get($key);
    }

    /**
     * Magic setter
     *
     * @param  string  $key  Key
     * @param  mixed   $val  Value
     *
     * @return void
     */
    public function __set($key, $val)
    {
        $this->set($key, $val);
    }

    /**
     * Magic isset check
     *
     * @param string $key
     *
     * @return boolean
     */
    public function __isset($key)
    {
        return array_key_exists($key, $this->vars);
    }

    /**
     * Magic unsetter
     *
     * @param string $key
     *
     * @return void
     */
    public function __unset($key)
    {
        if (! array_key_exists($key, $this->vars)) {
            return;
        }

        $this->vars[$key]->delete();
        $this->modified = true;

        $this->refreshIndex();
    }

    /**
     * Register the UUID of the given variable
     *
     * @param string $key
     * @param UuidInterface $uuid
     *
     * @return void
     */
    public function registerVarUuid(string $key, UuidInterface $uuid): static
    {
        $this->vars[$key]->setUuid($uuid);

        return $this;
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
            call_user_func($previousHandler, $e);
            die();
        }
    }
}
