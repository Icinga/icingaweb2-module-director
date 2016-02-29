<?php

namespace Icinga\Module\Director\IcingaConfig;

use Icinga\Exception\InvalidPropertyException;
use Icinga\Exception\ProgrammingError;
use Icinga\Module\Director\IcingaConfig\IcingaConfigHelper as c;
use Icinga\Module\Director\Objects\IcingaObject;

class ExtensibleSet
{
    protected $ownValues;

    protected $plusValues = array();

    protected $minusValues = array();

    protected $resolvedValues;

    protected $allowedValues;

    protected $inheritedValues = array();

    protected $fromDb = false;

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

        if ($set->foreignKey === null) {
            throw new ProgrammingError(
                'ExtensibleSet::forIcingaObject requires implementations with a defined $foreignKey'
            );
        }

        if ($object->hasBeenLoadedFromDb()) {
            $set->object = $object;
            $set->loadFromDb();
        }

        return $set;
    }

    public function hasBeenLoadedFromDb()
    {
        return $this->fromDb !== null;
    }

    public function hasBeenModified()
    {
        if ($this->hasBeenLoadedFromDb()) {

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

        $query = $db->select()->from(
            $this->tableName(),
            array(
                'property',
                'merge_behaviour'
            )
        )->where($this->foreignKey() . ' = ?', $this->object->id);

        $byBehaviour = array(
            'override'  => array(),
            'extend'    => array(),
            'blacklist' => array(),
        );

        foreach ($db->fetchAll($query) as $row) {
            if (! array_key_exists($row->merge_behaviour, byBehaviour)) {
                throw new ProgrammingError(
                    'Got unknown merge_behaviour "%s". Schema change?',
                    $row->merge_behaviour
                );
            }

            $byBehaviour[$row->merge_behaviour][] = $row->property;
        }

        foreach ($byBehaviour as $method => &$values) {
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
        return implode(
            '_',
            $this->object->getTableName(),
            $this->propertyName,
            'set'
        );
    }

    public function override($values)
    {
        $this->ownValues       = array();
        $this->inheritedValues = array();

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
            $this->reset();
        }

        $this->inheritedValues = array();

        $this->addValuesTo(
            $this->inheritedValues,
            $this->stripBlacklistedValues($parent->getResolvedValues())
        );

        return $this->recalculate();
    }

    public function forgetInheritedValues()
    {
        $this->inheritedValues = array();
        return $this;
    }

    public function renderAs($key, $prefix = '    ')
    {
        $parts = array();

        if ($this->ownValues !== null) {
            $parts[] = c::renderKeyValue(
                $key,
                c::renderArray($this->ownValues),
                $prefix
            );
        }

        if (! empty($this->plusValues)) {
            $parts[] = c::renderKeyOperatorValue(
                $key,
                '+=',
                c::renderArray($this->plusValues),
                $prefix
            );
        }

        if (! empty($this->minusValues)) {
            $parts[] = c::renderKeyOperatorValue(
                $key,
                '-=',
                c::renderArray($this->plusValues),
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
            $this->addTo($array, $value);
        }

        return $this;
    }

    protected function addResolvedValues($values)
    {
        if (! $this->hasBeenResolved()) {
            $this->resolvedValues  = array();
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
        $this->resolvedValues = array();
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
        $this->resolvedValues = null;

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

        return array($values);
    }
}
