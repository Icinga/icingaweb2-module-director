<?php

namespace Icinga\Module\Director\Web\Form;

use stdClass;
use Icinga\Module\Director\Objects\IcingaObject;
use Icinga\Module\Director\Objects\IcingaServiceSet;
use Icinga\Module\Director\Objects\DirectorDatafield;
use Zend_Form_Element as ZfElement;

class IcingaObjectFieldLoader
{
    protected $form;

    protected $object;

    protected $fields;

    protected $elements;

    public function __construct(IcingaObject $object)
    {
        $this->object = $object;
    }

    public function addFieldsToForm(QuickForm $form)
    {
        if ($this->object->supportsCustomVars()) {
            $this->attachFieldsToForm($form);
        }

        return $this;
    }

    /**
     * Set a list of values
     *
     * Works in a failsafe way, when a field does not exist the value will be
     * silently ignored
     *
     * @param Array  $values key/value pairs with variable names and their value
     * @param String $prefix An optional prefix that would be stripped from keys
     *
     * @return self
     */
    public function setValues($values, $prefix = null)
    {
        if (! $this->object->supportsCustomVars()) {
            return $this;
        }

        if ($prefix !== null) {
            $len = strlen($prefix);
        }
        $vars = $this->object->vars();

        foreach ($values as $key => $value) {
            if ($prefix) {
                if (substr($key, 0, $len) === $prefix) {
                    $key = substr($key, $len);
                } else {
                    continue;
                }
            }

            if ($el = $this->getElement($key)) {
                $el->setValue($value);
                $value = $el->getValue();

                if ($value === '') {
                    $value = null;
                }

                $vars->set($key, $value);
            }
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
     * Attach our form fields to the given form
     *
     * This will also create a 'Custom properties' display group
     */
    protected function attachFieldsToForm(QuickForm $form)
    {
        $elements = $this->getElements($form);

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
     * @return ZfElement[]
     */
    protected function createElements(QuickForm $form)
    {
        $elements = array();

        foreach ($this->getFields() as $name => $field) {
            $elements[$name] = $field->getFormElement($form);
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
     *
     * @return DirectorDatafield[]
     */
    protected function prepareObjectFields($object)
    {
        $fields = $this->loadResolvedFieldsForObject($object);
        if ($object->hasRelation('check_command')) {
            $command = $object->getResolvedRelated('check_command');
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
                'object_id'    => $idColumn,
                'is_required'  => 'f.is_required',
                'id'           => 'df.id',
                'varname'      => 'df.varname',
                'caption'      => 'df.caption',
                'description'  => 'df.description',
                'datatype'     => 'df.datatype',
                'format'       => 'df.format',
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
