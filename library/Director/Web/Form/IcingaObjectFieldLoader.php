<?php

namespace Icinga\Module\Director\Web\Form;

use Exception;
use Icinga\Data\Filter\Filter;
use Icinga\Data\Filter\FilterExpression;
use Icinga\Exception\IcingaException;
use Icinga\Module\Director\Objects\IcingaHost;
use Icinga\Module\Director\Objects\IcingaObject;
use Icinga\Module\Director\Objects\DirectorDatafield;
use Icinga\Module\Director\Objects\IcingaService;
use stdClass;
use Zend_Form_Element as ZfElement;

class IcingaObjectFieldLoader
{
    protected $form;

    protected $object;

    protected $fields;

    protected $elements;

    /** @var array Map element names to variable names 'elName' => 'varName' */
    protected $nameMap = array();

    public function __construct(IcingaObject $object)
    {
        $this->object = $object;
    }

    public function addFieldsToForm(QuickForm $form)
    {
        if ($this->fields || $this->object->supportsFields()) {
            $this->attachFieldsToForm($form);
        }

        return $this;
    }

    public function loadFieldsForMultipleObjects($objects)
    {
        $fields = array();
        foreach ($objects as $object) {
            foreach ($this->prepareObjectFields($object) as $varname => $field) {
                $varname = $field->varname;
                if (array_key_exists($varname, $fields)) {
                    if ($field->datatype !== $fields[$varname]->datatype) {
                        unset($fields[$varname]);
                    }

                    continue;
                }

                $fields[$field->varname] = $field;
            }
        }

        $this->fields = $fields;

        return $this;
    }

    /**
     * Set a list of values
     *
     * Works in a failsafe way, when a field does not exist the value will be
     * silently ignored
     *
     * @param array  $values key/value pairs with variable names and their value
     * @param String $prefix An optional prefix that would be stripped from keys
     *
     * @return self
     */
    public function setValues($values, $prefix = null)
    {
        if (! $this->object->supportsCustomVars()) {
            return $this;
        }

        if ($prefix === null) {
            $len = null;
        } else {
            $len = strlen($prefix);
        }
        $vars = $this->object->vars();

        foreach ($values as $key => $value) {
            if ($len !== null) {
                if (substr($key, 0, $len) === $prefix) {
                    $key = substr($key, $len);
                } else {
                    continue;
                }
            }

            $varName = $this->getElementVarName($prefix . $key);
            if ($varName === null) {
                // throw new IcingaException(
                //     'Cannot set variable value for "%s", got no such element',
                //     $key
                // );

                // Silently ignore additional fields. One might have switched
                // template or command
                continue;
            }

            $el = $this->getElement($varName);
            if ($el === null) {
                // throw new IcingaException('No such element %s', $key);
                // Same here.
                continue;
            }

            $el->setValue($value);
            $value = $el->getValue();
            if ($value === '' || $value === array()) {
                $value = null;
            }

            $vars->set($varName, $value);
        }

        return $this;
    }

    /**
     * Get the fields for our object
     *
     * @return DirectorDatafield[]
     */
    public function getFields()
    {
        if ($this->fields === null) {
            $this->fields = $this->prepareObjectFields($this->object);
        }

        return $this->fields;
    }

    /**
     * Get the form elements for our fields
     *
     * @param QuickForm $form Optional
     *
     * @return ZfElement[]
     */
    public function getElements(QuickForm $form = null)
    {
        if ($this->elements === null) {
            $this->elements = $this->createElements($form);
            $this->setValuesFromObject($this->object);
        }

        return $this->elements;
    }

    /**
     * Prepare the form elements for our fields
     *
     * @param QuickForm $form Optional
     *
     * @return self
     */
    public function prepareElements(QuickForm $form = null)
    {
        if ($this->object->supportsFields()) {
            $this->getElements($form);
        }

        return $this;
    }

    /**
     * Attach our form fields to the given form
     *
     * This will also create a 'Custom properties' display group
     *
     * @param DirectorObjectForm $form
     */
    protected function attachFieldsToForm(DirectorObjectForm $form)
    {
        if ($this->fields === null) {
            return;
        }
        $elements = $this->removeFilteredFields($this->getElements($form));

        foreach ($elements as $element) {
            $form->addElement($element);
        }

        if (! empty($elements)) {
            $form->addElementsToGroup(
                $elements,
                'custom_fields',
                50,
                $form->translate('Custom properties')
            );
        }
    }

    /**
     * @param ZfElement[] $elements
     * @return ZfElement[]
     */
    protected function removeFilteredFields(array $elements)
    {
        $filters = array();
        foreach ($this->fields as $key => $field) {
            if ($filter = $field->var_filter) {

                $filters[$key] = Filter::fromQueryString($filter);
            }
        }

        $kill = array();
        $columns = array();
        $object = $this->object;

        $object->invalidateResolveCache();
        $vars = $object::fromPlainObject(
            $object->toPlainObject(true),
            $object->getConnection()
        )->vars()->flatten();

        $prefixedVars = (object) array();
        if ($object instanceof IcingaHost) {
            $prefix = 'host.vars.';
        } elseif ($object instanceof IcingaService) {
            $prefix = 'service.vars.';
        } else {
            return $elements;
        }

        foreach ($vars as $k => $v) {
            $prefixedVars->{$prefix . $k} = $v;
        }

        foreach ($filters as $key => $filter) {
            /** @var $filter FilterChain|FilterExpression */
            foreach ($filter->listFilteredColumns() as $column) {
                $column = substr($column, strlen($prefix));
                $columns[$column] = $column;
            }
            if (! $filter->matches($prefixedVars)) {
                $kill[] = $key;
            }
        }

        foreach ($kill as $key) {
            unset($elements[$key]);
        }
        foreach ($columns as $col) {
            if (array_key_exists($col, $elements)) {
                $el = $elements[$col];
                $existingClass = $el->getAttrib('class');
                if (strlen($existingClass)) {
                    $el->setAttrib('class', $existingClass . ' autosubmit');
                } else {
                    $el->setAttrib('class', 'autosubmit');
                }
            }
        }

        return $elements;
    }

    protected function getElementVarName($name)
    {
        if (array_key_exists($name, $this->nameMap)) {
            return $this->nameMap[$name];
        }

        return null;
    }

    /**
     * Get the form element for a specific field by it's variable name
     *
     * @return ZfElement|null
     */
    protected function getElement($name)
    {
        $elements = $this->getElements();
        if (array_key_exists($name, $elements)) {
            return $this->elements[$name];
        }

        return null;
    }

    /**
     * Get the form elements based on the given form
     *
     * @param QuickForm $form
     *
     * @return ZfElement[]
     */
    protected function createElements(QuickForm $form)
    {
        $elements = array();

        foreach ($this->getFields() as $name => $field) {
            $el = $field->getFormElement($form);
            $elName = $el->getName();
            if (array_key_exists($elName, $this->nameMap)) {
                $form->addErrorMessage(sprintf(
                    'Form element name collision, "%s" resolves to "%s", but this is also used for "%s"',
                    $name,
                    $elName,
                    $this->nameMap[$elName]
                ));
            }
            $this->nameMap[$elName] = $name;
            $elements[$name] = $el;
        }

        return $elements;
    }

    protected function setValuesFromObject(IcingaObject $object)
    {
        foreach ($object->getVars() as $k => $v) {
            if ($v !== null && $el = $this->getElement($k)) {
                $el->setValue($v);
            }
        }
    }

    protected function mergeFields($listOfFields)
    {
        // TODO: Merge field for different object, mostly sets
    }

    /**
     * Create the fields for our object
     *
     * @param IcingaObject $object
     * @return DirectorDatafield[]
     */
    protected function prepareObjectFields($object)
    {
        $fields = $this->loadResolvedFieldsForObject($object);
        if ($object->hasRelation('check_command')) {
            try {
                $command = $object->getResolvedRelated('check_command');
            } catch (Exception $e) {
                // Ignore failures
                $command = null;
            }

            if ($command) {
                $cmdFields = $this->loadResolvedFieldsForObject($command);
                foreach ($cmdFields as $varname => $field) {
                    if (! array_key_exists($varname, $fields)) {
                        $fields[$varname] = $field;
                    }
                }
            }
        }

        return $fields;
    }

    /**
     * Create the fields for our object
     *
     * Follows the inheritance logic, resolves all fields and keeps the most
     * specific ones. Returns a list of fields indexed by variable name
     *
     * @return DirectorDatafield[]
     */
    protected function loadResolvedFieldsForObject($object)
    {
        $result = $this->loadDataFieldsForObjects(
            array_merge(
                $object->templateResolver()->fetchResolvedParents(),
                array($object)
            )
        );

        $fields = array();
        foreach ($result as $objectId => $varFields) {
            foreach ($varFields as $var => $field) {
                $fields[$var] = $field;
            }
        }

        return $fields;
    }

    /**
     * Fetches fields for a given List of objects from the database
     *
     * Gives a list indexed by object id, with each entry being a list of that
     * objects DirectorDatafield instances indexed by variable name
     *
     * @param IcingaObject[] $objectList List of objects
     *
     * @return Array
     */
    protected function loadDataFieldsForObjects($objectList)
    {
        $ids = array();
        $objects = array();
        foreach ($objectList as $object) {
            if ($object->hasBeenLoadedFromDb()) {
                $ids[] = $object->id;
                $objects[$object->id] = $object;
            }
        }

        if (empty($ids)) {
            return array();
        }

        $connection = $object->getConnection();
        $db = $connection->getDbAdapter();

        $idColumn = 'f.' . $object->getShortTableName() . '_id';

        $query = $db->select()->from(
            array('df' => 'director_datafield'),
            array(
                'object_id'   => $idColumn,
                'var_filter'  => 'f.var_filter',
                'is_required' => 'f.is_required',
                'id'          => 'df.id',
                'varname'     => 'df.varname',
                'caption'     => 'df.caption',
                'description' => 'df.description',
                'datatype'    => 'df.datatype',
                'format'      => 'df.format',
            )
        )->join(
            array('f' => $object->getTableName() . '_field'),
            'df.id = f.datafield_id',
            array()
        )->where($idColumn . ' IN (?)', $ids)
         ->order('df.caption ASC');

        $res = $db->fetchAll($query);

        $result = array();
        foreach ($res as $r) {
            $id = $r->object_id;
            unset($r->object_id);

            $r->object = $objects[$id];
            if (! array_key_exists($id, $result)) {
                $result[$id] = new stdClass;
            }

            $result[$id]->{$r->varname} = DirectorDatafield::fromDbRow(
                $r,
                $connection
            );
        }

        return $result;
    }
}
