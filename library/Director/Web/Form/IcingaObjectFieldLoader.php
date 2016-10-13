<?php

namespace Icinga\Module\Director\Web\Form;

use Icinga\Module\Director\Exception\NestingError;
use Icinga\Module\Director\Objects\IcingaServiceSet;
use Icinga\Module\Director\Objects\DirectorDatafield;
use stdClass;

class IcingaObjectFieldLoader
{
    protected $form;

    protected $object;

    protected function __construct(DirectorObjectForm $form)
    {
        $this->form = $form;        
        $this->object = $form->getObject();
    }

    public static function addFieldsToForm(DirectorObjectForm $form, & $values)
    {
        if (! $form->getObject()->supportsCustomVars()) {
            return $form;
        }

        $loader = new static($form);
        $loader->addFields();
        if ($values !== null) {
            $loader->setValues($loader->stripKeyPrefix($values, 'var_'));
        }

        return $form;
    }

    protected function stripKeyPrefix($array, $prefix)
    {
        $new = array();
        $len = strlen($prefix);
        foreach ($array as $key => $value) {
            if (substr($key, 0, $len) === $prefix) {
                $new[substr($key, $len)] = $value;
            }
        }

        return $new;
    }

    protected function setValues($values)
    {
        $vars = $this->object->vars();
        $form = $this->form;

        foreach ($values as $key => $value) {
            if ($el = $form->getElement('var_' . $key)) {
                if ($value === '' || $value === null) {
                    continue;
                }
                $el->setValue($value);
                $vars->set($key, $el->getValue());
            }
        }
    }

    protected function addFields()
    {
        $object = $this->object;
        if ($object instanceof IcingaServiceSet) {
        } else {
            $this->attachFields(
                $this->prepareObjectFields($object)
            );
        }

        $this->setValues($object->getVars());
    }

    protected function attachFields($fields)
    {
        $form = $this->form;
        $elements = array();
        foreach ($fields as $field) {
            $elements[] = $field->getFormElement($form);
        }

        if (empty($elements)) {
            return $this;
        }

        return $form->addElementsToGroup(
            $elements,
            'custom_fields',
            50,
            $form->translate('Custom properties')
        );
    }

    protected function prepareObjectFields($object)
    {
        $fields = $this->loadResolvedFieldsForObject($object);

        if ($object->hasProperty('command_id')) {
            $command = $object->getResolvedRelated('command');
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

    protected function mergeFields($listOfFields)
    {
        // TODO: Merge field for different object, mostly sets
    }

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

    protected function getDb()
    {
        return $this->form->getDb();
    }

    protected function loadDataFieldsForObjects($objectList)
    {
        if (empty($objectList)) {
            // Or should we fail?
            return array();
        }

        $ids = array();
        $objects = array();
        foreach ($objectList as $object) {
            $ids[] = $object->id;
            $objects[$object->id] = $object;
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
