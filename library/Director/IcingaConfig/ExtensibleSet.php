<?php

namespace Icinga\Module\Director\IcingaConfig;

use Icinga\Exception\InvalidPropertyException;
use Icinga\Exception\ProgrammingError;
use Icinga\Module\Director\IcingaConfig\IcingaConfigHelper as c;
use Icinga\Module\Director\Objects\IcingaObject;

class ExtensibleSet
{
    protected $ownValues;

    protected $plusValues = [];

    protected $minusValues = [];

    protected $resolvedValues;

    protected $allowedValues;

    protected $inheritedValues = [];

    protected $fromDb;

    /**
     * @var IcingaObject
     */
    protected $object;

    /**
     * Object property name pointing to this set
     *
     * This also implies set table called <object_table>_<propertyName>_set
     *
     * @var string
     */
    protected $propertyName;

    public function __construct($values = null)
    {
        if (null !== $values) {
            $this->override($values);
        }
    }

    public static function forIcingaObject(IcingaObject $object, $propertyName)
    {
        $set = new static;
        $set->object = $object;
        $set->propertyName = $propertyName;

        if ($object->hasBeenLoadedFromDb()) {
            $set->loadFromDb();
        }

        return $set;
    }

    public function set($set)
    {
        if (null === $set) {
            $this->reset();

            return $this;
        } elseif (is_array($set) || is_string($set)) {
            $this->reset();
            $this->override($set);
        } elseif (is_object($set)) {
            $this->reset();

            foreach (['override', 'extend', 'blacklist'] as $method) {
                if (property_exists($set, $method)) {
                    $this->$method($set->$method);
                }
            }
        } else {
            throw new ProgrammingError(
                'ExtensibleSet::set accepts only plain arrays or objects'
            );
        }

        return $this;
    }

    public function isEmpty()
    {
        return $this->ownValues === null
            && empty($this->plusValues)
            && empty($this->minusValues);
    }

    public function toPlainObject()
    {
        if ($this->ownValues !== null) {
            if (empty($this->minusValues) && empty($this->plusValues)) {
                return $this->ownValues;
            }
        }

        $plain = (object) [];

        if ($this->ownValues !== null) {
            $plain->override = $this->ownValues;
        }
        if (! empty($this->plusValues)) {
            $plain->extend = $this->plusValues;
        }
        if (! empty($this->minusValues)) {
            $plain->blacklist = $this->minusValues;
        }

        return $plain;
    }

    public function getPlainUnmodifiedObject()
    {
        if ($this->fromDb === null) {
            return null;
        }
        
        $old = $this->fromDb;

        if ($old['override'] !== null) {
            if (empty($old['blacklist']) && empty($old['extend'])) {
                return $old['override'];
            }
        }

        $plain = (object) [];

        if ($old['override'] !== null) {
            $plain->override = $old['override'];
        }
        if (! empty($old['extend'])) {
            $plain->extend = $old['extend'];
        }
        if (! empty($old['blacklist'])) {
            $plain->blacklist = $old['blacklist'];
        }

        return $plain;
    }

    public function hasBeenLoadedFromDb()
    {
        return $this->fromDb !== null;
    }

    public function hasBeenModified()
    {
        if ($this->hasBeenLoadedFromDb()) {
            if ($this->ownValues !== $this->fromDb['override']) {
                return true;
            }

            if ($this->plusValues !== $this->fromDb['extend']) {
                return true;
            }

            if ($this->minusValues !== $this->fromDb['blacklist']) {
                return true;
            }

            return false;
        } else {
            if ($this->ownValues === null
                && empty($this->plusValues)
                && empty($this->minusValues)
            ) {
                return false;
            } else {
                return true;
            }
        }
    }

    protected function loadFromDb()
    {
        $db = $this->object->getDb();

        $query = $db->select()->from($this->tableName(), [
            'property',
            'merge_behaviour'
        ])->where($this->foreignKey() . ' = ?', $this->object->get('id'));

        $byBehaviour = [
            'override'  => [],
            'extend'    => [],
            'blacklist' => [],
        ];

        foreach ($db->fetchAll($query) as $row) {
            if (! array_key_exists($row->merge_behaviour, $byBehaviour)) {
                throw new ProgrammingError(
                    'Got unknown merge_behaviour "%s". Schema change?',
                    $row->merge_behaviour
                );
            }

            $byBehaviour[$row->merge_behaviour][] = $row->property;
        }

        foreach ($byBehaviour as $method => &$values) {
            if (empty($values)) {
                continue;
            }

            sort($values);
            $this->$method($values);
        }

        if (empty($byBehaviour['override'])) {
            $byBehaviour['override'] = null;
        }

        $this->fromDb = $byBehaviour;

        return $this;
    }

    protected function foreignKey()
    {
        return $this->object->getShortTableName() . '_id';
    }

    protected function tableName()
    {
        return implode('_', [
            $this->object->getTableName(),
            $this->propertyName,
            'set'
        ]);
    }

    public function getObject()
    {
        return $this->object;
    }

    public function store()
    {
        if (null === $this->object) {
            throw new ProgrammingError(
                'Cannot store ExtensibleSet with no assigned object'
            );
        }

        if (! $this->hasBeenModified()) {
            return false;
        }

        $this->storeToDb();
        return true;
    }

    protected function storeToDb()
    {
        $db = $this->object->getDb();

        if ($db === null) {
            throw new ProgrammingError(
                'Cannot store a set for an unstored related object'
            );
        }

        $table = $this->tableName();
        $props = [
            $this->foreignKey() => $this->object->get('id')
        ];

        $db->delete(
            $this->tableName(),
            $db->quoteInto(
                $this->foreignKey() . ' = ?',
                $this->object->get('id')
            )
        );

        if ($this->ownValues !== null) {
            $props['merge_behaviour'] = 'override';
            foreach ($this->ownValues as $value) {
                $db->insert(
                    $table,
                    array_merge($props, ['property' => $value])
                );
            }
        }

        if (! empty($this->plusValues)) {
            $props['merge_behaviour'] = 'extend';
            foreach ($this->plusValues as $value) {
                $db->insert(
                    $table,
                    array_merge($props, ['property' => $value])
                );
            }
        }

        if (! empty($this->minusValues)) {
            $props['merge_behaviour'] = 'blacklist';
            foreach ($this->minusValues as $value) {
                $db->insert(
                    $table,
                    array_merge($props, ['property' => $value])
                );
            }
        }

        $this->setBeingLoadedFromDb();
    }

    public function setBeingLoadedFromDb()
    {
        $this->fromDb = [
            'override'  => $this->ownValues ?: [],
            'extend'    => $this->plusValues ?: [],
            'blacklist' => $this->minusValues ?: [],
        ];
    }

    public function override($values)
    {
        $this->ownValues       = [];
        $this->inheritedValues = [];

        $this->addValuesTo($this->ownValues, $values);

        return $this->addResolvedValues($values);
    }

    public function extend($values)
    {
        $this->addValuesTo($this->plusValues, $values);
        return $this->addResolvedValues($values);
    }

    public function blacklist($values)
    {
        $this->addValuesTo($this->minusValues, $values);

        if ($this->hasBeenResolved()) {
            $this->removeValuesFrom($this->resolvedValues, $values);
        }

        return $this;
    }

    public function getResolvedValues()
    {
        if (! $this->hasBeenResolved()) {
            $this->recalculate();
        }

        sort($this->resolvedValues);

        return $this->resolvedValues;
    }

    public function inheritFrom(ExtensibleSet $parent)
    {
        if ($this->ownValues !== null) {
            return $this;
        }

        if ($this->hasBeenResolved()) {
            $this->resolvedValues = null;
        }

        $this->inheritedValues = [];

        $this->addValuesTo(
            $this->inheritedValues,
            $this->stripBlacklistedValues($parent->getResolvedValues())
        );

        return $this->recalculate();
    }

    public function forgetInheritedValues()
    {
        $this->inheritedValues = [];
        return $this;
    }

    protected function renderArray($array)
    {
        $safe = [];
        foreach ($array as $value) {
            $safe[] = c::alreadyRendered($value);
        }

        return c::renderArray($safe);
    }

    public function renderAs($key, $prefix = '    ')
    {
        $parts = [];

        // TODO: It would be nice if we could use empty arrays to override
        //       inherited ones
        // if ($this->ownValues !== null) {
        if (!empty($this->ownValues)) {
            $parts[] = c::renderKeyValue(
                $key,
                $this->renderArray($this->ownValues),
                $prefix
            );
        }

        if (!empty($this->plusValues)) {
            $parts[] = c::renderKeyOperatorValue(
                $key,
                '+=',
                $this->renderArray($this->plusValues),
                $prefix
            );
        }

        if (!empty($this->minusValues)) {
            $parts[] = c::renderKeyOperatorValue(
                $key,
                '-=',
                $this->renderArray($this->minusValues),
                $prefix
            );
        }

        return implode('', $parts);
    }

    public function isRestricted()
    {
        return $this->allowedValues === null;
    }

    public function enumAllowedValues()
    {
        if ($this->isRestricted()) {
            throw new ProgrammingError(
                'No allowed value set available, this set is not restricted'
            );
        }

        if (empty($this->allowedValues)) {
            return [];
        }

        return array_combine($this->allowedValues, $this->allowedValues);
    }

    protected function hasBeenResolved()
    {
        return $this->resolvedValues !== null;
    }

    protected function stripBlacklistedValues($array)
    {
        $this->removeValuesFrom($array, $this->minusValues);

        return $array;
    }

    protected function assertValidValue($value)
    {
        if (null === $this->allowedValues) {
            return $this;
        }

        if (in_array($value, $this->allowedValues)) {
            return $this;
        }

        throw new InvalidPropertyException(
            'Got invalid property "%s", allowed are: (%s)',
            $value,
            implode(', ', $this->allowedValues)
        );
    }

    protected function addValuesTo(&$array, $values)
    {
        foreach ($this->wantArray($values) as $value) {
            // silently ignore null or empty strings
            if (strlen($value) === 0) {
                continue;
            }

            $this->addTo($array, $value);
        }

        return $this;
    }

    protected function addResolvedValues($values)
    {
        if (! $this->hasBeenResolved()) {
            $this->resolvedValues  = [];
        }

        return $this->addValuesTo(
            $this->resolvedValues,
            $this->stripBlacklistedValues($this->wantArray($values))
        );
    }

    protected function removeValuesFrom(&$array, $values)
    {
        foreach ($this->wantArray($values) as $value) {
            $this->removeFrom($array, $value);
        }

        return $this;
    }

    protected function addTo(&$array, $value)
    {
        if (! in_array($value, $array)) {
            $this->assertValidValue($value);
            $array[] = $value;
        }

        return $this;
    }

    protected function removeFrom(&$array, $value)
    {
        if (false !== ($pos = array_search($value, $array))) {
            unset($array[$pos]);
        }

        return $this;
    }

    protected function recalculate()
    {
        $this->resolvedValues = [];

        if ($this->ownValues === null) {
            $this->addValuesTo($this->resolvedValues, $this->inheritedValues);
        } else {
            $this->addValuesTo($this->resolvedValues, $this->ownValues);
        }
        $this->addValuesTo($this->resolvedValues, $this->plusValues);
        $this->removeFrom($this->resolvedValues, $this->minusValues);

        return $this;
    }

    protected function reset()
    {
        $this->ownValues = null;
        $this->plusValues = [];
        $this->minusValues = [];
        $this->resolvedValues = null;
        $this->inheritedValues = [];

        return $this;
    }

    protected function translate($string)
    {
        return mt('director', $string);
    }

    protected function wantArray($values)
    {
        if (is_array($values)) {
            return $values;
        }

        return [$values];
    }
}
