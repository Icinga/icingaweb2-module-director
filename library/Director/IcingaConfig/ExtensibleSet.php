<?php

namespace Icinga\Module\Director\IcingaConfig;

use Icinga\Exception\InvalidPropertyException;

class ExtensibleSet
{
    protected $ownValues;

    protected $plusValues = array();

    protected $minusValues = array();

    protected $resolvedValues;

    protected $allowedValues;

    protected $inheritedValues = array();

    public function __construct($values = null)
    {
        if (null !== $values) {
            $this->override($values);
        }
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

    protected function hasBeenResolved()
    {
        return $this->resolvedValues !== null;
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

    protected function stripBlacklistedValues($array)
    {
        $this->removeValuesFrom($array, $this->minusValues);

        return $array;
    }

    public function forgetInheritedValues()
    {
        $this->inheritedValues = array();
        return $this;
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

    protected function wantArray($values)
    {
        if (is_array($values)) {
            return $values;
        }

        return array($values);
    }
}
