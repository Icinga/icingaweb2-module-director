<?php

namespace Icinga\Module\Director\Objects;

use Icinga\Data\Filter\Filter;
use Icinga\Exception\IcingaException;
use Icinga\Exception\ProgrammingError;
use Icinga\Module\Director\IcingaConfig\AssignRenderer;
use Icinga\Module\Director\IcingaConfig\IcingaConfigHelper as c;

class IcingaObjectAssignments
{
    protected $object;

    protected $stored;

    protected $current;

    public function __construct(IcingaObject $object)
    {
        if (! $object->supportsAssignments()) {
            if ($object->hasProperty('object_type')) {
                $type = $object->object_type;
            } else {
                $type = get_class($object);
            }

            throw new ProgrammingError(
                'I can only assign for applied objects, got %s',
                $type
            );
        }

        $this->object = $object;
    }

    public function store()
    {
        if ($this->hasBeenModified()) {
            $this->reallyStore();
            return true;
        }

        return false;
    }

    public function setValues($values)
    {
        if (is_string($values)) {
            return $this->setValues(array($values));
        }

        if (is_object($values)) {
            $values = (array) $values;
        }

        $this->current = array();

        ksort($values);
        foreach ($values as $type => $value) {
            if (is_numeric($type)) {
                $this->addRule($value);
            } else {
                if (is_string($value)) {
                    $this->addRule($value, $type);
                    continue;
                }

                foreach ($value as $key => $strings) {
                    $this->addRule($strings, $type);
                }
            }
        }

        return $this;
    }

    public function getFormValues()
    {
        $result = array();
        foreach ($this->getCurrent() as $rule) {
            $f = array(
                'assign_type' => $rule['assign_type']
            );

            $filter = Filter::fromQueryString($rule['filter_string']);
            if (!$filter->isExpression()) {
                throw new IcingaException(
                    'We currently support only flat filters in our forms, got %',
                    (string) $filter
                );
            }

            $f['property']   = $filter->getColumn();
            $f['operator']   = $filter->getSign();
            $f['expression'] = trim(stripcslashes($filter->getExpression()), '"');

            $result[] = $f;
        }

        return $result;
    }

    public function setFormValues($values)
    {
        $rows = array();

        foreach ($values as $key => $val) {
            if (! is_numeric($key)) {
                // Skip buttons or similar
                continue;
            }

            if (!array_key_exists($val['assign_type'], $rows)) {
                $rows[$val['assign_type']] = array();
            }

            if (array_key_exists('filter_string', $val)) {
                $filter = $val['filter_string'];

                if (! $filter->isEmpty()) {
                    $rows[$val['assign_type']][] = $filter;
                }

                continue;
            }

            if (empty($val['property'])) {
                continue;
            }

            if (is_numeric($val['expression'])) {
                $expression = $val['expression'];
            } else {
                $expression = '"' . addcslashes($val['expression'], '"') . '"';
            }

            $rows[$val['assign_type']][] = $this->rerenderFilter(
                implode('', array(
                    $val['property'],
                    $val['operator'],
                    $expression,
                ))
            );

        }

        return $this->setValues($rows);
    }

    protected function addRule($string, $type = 'assign')
    {
        if (is_array($string) && array_key_exists('assign_type', $string)) {
            $type = $string['assign_type'];
            $string = $string['filter_string'];
        }
        // TODO: validate
        //echo "ADD RULE\n";
        //var_dump($string);
        //echo "ADD RULE END\n";
        $this->current[] = array(
            'assign_type'   => $type,
            'filter_string' => $string instanceof Filter ? $this->renderFilter($string) : $string
        );

        return $this;
    }

    public function getValues()
    {
        return $this->getCurrent();
    }

    public function getUnmodifiedValues()
    {
        return $this->getStored();
    }

    public function toConfigString()
    {
        return $this->renderRules($this->getCurrent());
    }

    public function toUnmodifiedConfigString()
    {
        return $this->renderRules($this->getStored());
    }

    protected function renderRules($rules)
    {
        if (empty($rules)) {
            return '';
        }

        $filters = array();

        foreach ($rules as $rule) {
            $filters[] = AssignRenderer::forFilter(
                Filter::fromQueryString($rule['filter_string'])
            )->render($rule['assign_type']);
        }

        return "\n    " . implode("\n    ", $filters) . "\n";
    }

    public function getPlain()
    {
        if ($this->current === null) {
            if (! $this->object->hasBeenLoadedFromDb()) {
                return array();
            }

            $this->current = $this->getStored();
        }

        return $this->createPlain($this->current);
    }

    public function getUnmodifiedPlain()
    {
        if (! $this->object->hasBeenLoadedFromDb()) {
            return array();
        }

        return $this->createPlain($this->getStored());
    }

    public function hasBeenModified()
    {
        if ($this->current === null) {
            return false;
        }

        return json_encode($this->getCurrent()) !== json_encode($this->getStored());
    }

    protected function getCurrent()
    {
        if ($this->current === null) {
            $this->current = $this->getStored();
        }

        return $this->current;
    }

    protected function getStored()
    {
        if ($this->stored === null) {
            $this->stored = $this->loadFromDb();
        }

        return $this->stored;
    }

    protected function renderFilter(Filter $filter)
    {
        return rawurldecode($filter->toQueryString());
    }

    protected function rerenderFilter($string)
    {
        return $this->renderFilterFilter::fromQueryString($string);
    }

    protected function createPlain($dbRows)
    {
        $result = array();
        foreach ($dbRows as $row) {
            if (! array_key_exists($row['assign_type'], $result)) {
                $result[$row['assign_type']] = array();
            }

            $result[$row['assign_type']][] = $row['filter_string'];
        }

        return $result;
    }

    protected function getDb()
    {
        return $this->object->getDb();
    }

    protected function loadFromDb()
    {
        $db = $this->getDb();
        $object = $this->object;

        $query = $db->select()->from(
            $this->getTableName(),
            array('assign_type', 'filter_string')
        )->where($this->createWhere())->order('assign_type', 'filter_string');

        $this->stored = array();
        foreach ($db->fetchAll($query) as $row) {
            $this->stored[] = (array) $row;
        }

        return $this->stored;
    }

    protected function createWhere()
    {
        return $this->getRelationColumn()
            . ' = '
            . $this->getObjectId();
    }

    protected function getObjectId()
    {
        return (int) $this->object->id;
    }

    protected function getRelationColumn()
    {
        return $this->object->getShortTableName() . '_id';
    }

    protected function getTableName()
    {
        return $this->object->getTableName() . '_assignment';
    }

    protected function reallyStore()
    {
        $db          = $this->getDb();
        $table       = $this->getTableName();
        $objectId    = $this->object->id;
        $relationCol = $this->getRelationColumn();

        $db->delete($table, $this->createWhere());

        foreach ($this->getCurrent() as $row) {
            $data = (array) $row;
            $data[$relationCol] = $objectId;
            $db->insert($table, $data);
        }

        $this->stored = $this->current;

        return $this;
    }
}
