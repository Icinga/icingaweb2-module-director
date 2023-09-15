<?php

namespace Icinga\Module\Director\Web\Form;

use Exception;
use Icinga\Data\Filter\Filter;
use Icinga\Data\Filter\FilterChain;
use Icinga\Data\Filter\FilterExpression;
use Icinga\Exception\IcingaException;
use Icinga\Module\Director\Hook\HostFieldHook;
use Icinga\Module\Director\Hook\ServiceFieldHook;
use Icinga\Module\Director\Objects\DirectorDatafieldCategory;
use Icinga\Module\Director\Objects\IcingaCommand;
use Icinga\Module\Director\Objects\IcingaHost;
use Icinga\Module\Director\Objects\IcingaObject;
use Icinga\Module\Director\Objects\DirectorDatafield;
use Icinga\Module\Director\Objects\IcingaService;
use Icinga\Module\Director\Objects\ObjectApplyMatches;
use Icinga\Web\Hook;
use stdClass;
use Zend_Db_Select as ZfSelect;
use Zend_Form_Element as ZfElement;

class IcingaObjectFieldLoader
{
    protected $form;

    /** @var IcingaObject */
    protected $object;

    /** @var \Icinga\Module\Director\Db */
    protected $connection;

    /** @var \Zend_Db_Adapter_Abstract */
    protected $db;

    /** @var DirectorDatafield[] */
    protected $fields;

    protected $elements;

    protected $forceNull = array();

    /** @var array Map element names to variable names 'elName' => 'varName' */
    protected $nameMap = array();

    public function __construct(IcingaObject $object)
    {
        $this->object = $object;
        $this->connection = $object->getConnection();
        $this->db = $this->connection->getDbAdapter();
    }

    public function addFieldsToForm(DirectorObjectForm $form)
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
                $varname = $field->get('varname');
                if (array_key_exists($varname, $fields)) {
                    if ($field->get('datatype') !== $fields[$varname]->datatype) {
                        unset($fields[$varname]);
                    }

                    continue;
                }

                $fields[$varname] = $field;
            }
        }

        $this->fields = $fields;

        return $this;
    }

    /**
     * Set a list of values
     *
     * Works in a fail-safe way, when a field does not exist the value will be
     * silently ignored
     *
     * @param array  $values key/value pairs with variable names and their value
     * @param string $prefix An optional prefix that would be stripped from keys
     *
     * @return IcingaObjectFieldLoader
     *
     * @throws IcingaException
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

        // Hint: this does currently not happen, as removeFilteredFields did not
        //       take place yet. This has been added to be on the safe side when
        //       cleaning things up one future day
        foreach ($this->forceNull as $key) {
            $vars->set($key, null);
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
     * @param DirectorObjectForm $form Optional
     *
     * @return ZfElement[]
     */
    public function getElements(DirectorObjectForm $form = null)
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
     * @param DirectorObjectForm $form Optional
     *
     * @return self
     */
    public function prepareElements(DirectorObjectForm $form = null)
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

        $this->attachGroupElements($elements, $form);
    }

    /**
     * @param ZfElement[]        $elements
     * @param DirectorObjectForm $form
     */
    protected function attachGroupElements(array $elements, DirectorObjectForm $form)
    {
        $categories = [];
        $categoriesFetchedById = [];
        foreach ($this->fields as $key => $field) {
            if ($id = $field->get('category_id')) {
                if (isset($categoriesFetchedById[$id])) {
                    $category = $categoriesFetchedById[$id];
                } else {
                    $category = DirectorDatafieldCategory::loadWithAutoIncId($id, $form->getDb());
                    $categoriesFetchedById[$id] = $category;
                }
            } elseif ($field->hasCategory()) {
                $category = $field->getCategory();
            } else {
                continue;
            }
            $categories[$key] = $category;
        }
        $prioIdx = \array_flip(\array_keys($categories));

        foreach ($elements as $key => $element) {
            if (isset($categories[$key])) {
                $category = $categories[$key];
                $form->addElementsToGroup(
                    [$element],
                    'custom_fields:' . $category->get('category_name'),
                    DirectorObjectForm::GROUP_ORDER_CUSTOM_FIELD_CATEGORIES + $prioIdx[$key],
                    $category->get('category_name')
                );
            } else {
                $form->addElementsToGroup(
                    [$element],
                    'custom_fields',
                    DirectorObjectForm::GROUP_ORDER_CUSTOM_FIELDS,
                    $form->translate('Custom properties')
                );
            }
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
        if ($object instanceof IcingaHost) {
            $prefix = 'host.vars.';
        } elseif ($object instanceof IcingaService) {
            $prefix = 'service.vars.';
        } else {
            return $elements;
        }

        $object->invalidateResolveCache();
        $vars = $object::fromPlainObject(
            $object->toPlainObject(true),
            $object->getConnection()
        )->getVars();

        $prefixedVars = (object) array();
        foreach ($vars as $k => $v) {
            $prefixedVars->{$prefix . $k} = $v;
        }

        foreach ($filters as $key => $filter) {
            ObjectApplyMatches::fixFilterColumns($filter);
            /** @var $filter FilterChain|FilterExpression */
            foreach ($filter->listFilteredColumns() as $column) {
                $column = substr($column, strlen($prefix));
                $columns[$column] = $column;
            }
            if (! $filter->matches($prefixedVars)) {
                $kill[] = $key;
            }
        }

        $vars = $object->vars();
        foreach ($kill as $key) {
            unset($elements[$key]);
            $this->forceNull[$key] = $key;
            // Hint: this should happen later on, currently execution order is
            //       a little bit weird
            $vars->set($key, null);
        }

        foreach ($columns as $col) {
            if (array_key_exists($col, $elements)) {
                $el = $elements[$col];
                $existingClass = $el->getAttrib('class');
                if ($existingClass !== null && strlen($existingClass)) {
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
     * @param  string $name
     * @return null|ZfElement
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
     * @param DirectorObjectForm $form
     *
     * @return ZfElement[]
     */
    protected function createElements(DirectorObjectForm $form)
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

    /**
     * @param IcingaObject $object
     */
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
            #for checks
            $relation = "check_command";
        } elseif ($object->hasRelation('command')) {
            #for notifications
            $relation = "command";
        }
        
        try {
            /** @var IcingaCommand $command */
            $command = $object->getResolvedRelated($relation);
        } catch (Exception $e) {
            // Ignore failures
            $command = null;
        }

        if ($command) {
            $cmdLoader = new static($command);
            $cmdFields = $cmdLoader->getFields();
            foreach ($cmdFields as $varname => $field) {
                if (!array_key_exists($varname, $fields)) {
                    $fields[$varname] = $field;
                }
            }
        }
        
        // TODO -> filters!
        return $fields;
    }

    /**
     * Create the fields for our object
     *
     * Follows the inheritance logic, resolves all fields and keeps the most
     * specific ones. Returns a list of fields indexed by variable name
     *
     * @param IcingaObject $object
     *
     * @return DirectorDatafield[]
     */
    protected function loadResolvedFieldsForObject(IcingaObject $object)
    {
        $result = $this->loadDataFieldsForObject(
            $object
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
     * @param IcingaObject[] $objectList List of objects
     *
     * @return array
     */
    protected function getIdsForObjectList($objectList)
    {
        $ids = [];
        foreach ($objectList as $object) {
            if ($object->hasBeenLoadedFromDb()) {
                $ids[] = $object->get('id');
            }
        }

        return $ids;
    }

    public function fetchFieldDetailsForObject(IcingaObject $object)
    {
        $ids = $object->listAncestorIds();
        if ($id = $object->getProperty('id')) {
            $ids[] = $id;
        }
        return $this->fetchFieldDetailsForIds($ids);
    }

    /***
     * @param $objectIds
     *
     * @return \stdClass[]
     */
    protected function fetchFieldDetailsForIds($objectIds)
    {
        if (empty($objectIds)) {
            return [];
        }

        $query = $this->prepareSelectForIds($objectIds);
        return $this->db->fetchAll($query);
    }

    /**
     * @param array $ids
     *
     * @return ZfSelect
     */
    protected function prepareSelectForIds(array $ids)
    {
        $object = $this->object;

        $idColumn = 'f.' . $object->getShortTableName() . '_id';

        $query = $this->db->select()->from(
            array('df' => 'director_datafield'),
            array(
                'object_id'   => $idColumn,
                'icinga_type' => "('" . $object->getShortTableName() . "')",
                'var_filter'  => 'f.var_filter',
                'is_required' => 'f.is_required',
                'id'          => 'df.id',
                'category_id' => 'df.category_id',
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
            ->order('CASE WHEN var_filter IS NULL THEN 0 ELSE 1 END ASC')
            ->order('df.caption ASC');

        return $query;
    }

    /**
     * Fetches fields for a given object
     *
     * Gives a list indexed by object id, with each entry being a list of that
     * objects DirectorDatafield instances indexed by variable name
     *
     * @param IcingaObject $object
     *
     * @return array
     */
    public function loadDataFieldsForObject(IcingaObject $object)
    {
        $res = $this->fetchFieldDetailsForObject($object);

        $result = [];
        foreach ($res as $r) {
            $id = $r->object_id;
            unset($r->object_id);
            if (! array_key_exists($id, $result)) {
                $result[$id] = new stdClass;
            }

            $result[$id]->{$r->varname} = DirectorDatafield::fromDbRow(
                $r,
                $this->connection
            );
        }

        foreach ($this->loadHookedDataFieldForObject($object) as $id => $fields) {
            if (array_key_exists($id, $result)) {
                foreach ($fields as $varName => $field) {
                    $result[$id]->$varName = $field;
                }
            } else {
                $result[$id] = $fields;
            }
        }

        return $result;
    }

    /**
     * @param IcingaObject $object
     * @return array
     */
    protected function loadHookedDataFieldForObject(IcingaObject $object)
    {
        $fields = [];
        if ($object instanceof IcingaHost || $object instanceof  IcingaService) {
            $fields = $this->addHookedFields($object);
        }

        return $fields;
    }

    /**
     * @param IcingaObject $object
     * @return mixed
     */
    protected function addHookedFields(IcingaObject $object)
    {
        $fields = [];
        /** @var HostFieldHook|ServiceFieldHook $hook */
        $type = ucfirst($object->getShortTableName());
        foreach (Hook::all("Director\\{$type}Field") as $hook) {
            if ($hook->wants($object)) {
                $id = $object->get('id');
                $spec = $hook->getFieldSpec($object);
                if (!array_key_exists($id, $fields)) {
                    $fields[$id] = new stdClass();
                }
                $fields[$id]->{$spec->getVarName()} = $spec->toDataField($object);
            }
        }
        return $fields;
    }
}
