<?php

namespace Icinga\Module\Director\Web\Form;

use Icinga\Module\Director\Objects\IcingaObject;
use Icinga\Module\Director\Objects\DirectorDatafield;
use Zend_Form_Element_Select as Zf_Select;

abstract class DirectorObjectForm extends QuickForm
{
    protected $db;

    protected $object;

    protected $objectName;

    protected $className;

    protected $deleteButtonName;

    protected $objectType = 'object';

    protected $fieldsDisplayGroup;

    protected $displayGroups = array();

    protected function object($values = array())
    {
        if ($this->object === null) {
            $class = $this->getObjectClassname();
            $this->object = $class::create($values, $this->db);
            foreach ($this->getValues() as $key => $value) {
                if ($this->object->hasProperty($key)) {
                    $this->object->$key = $value;
                }
            }
        } else {
            if (! $this->object->hasConnection()) {
                $this->object->setConnection($this->db);
            }
            $this->object->setProperties($values);
        }

        return $this->object;
    }

    protected function isTemplate()
    {
        return $this->objectType === 'template';
    }

    protected function handleImports($object, & $values)
    {
        if (! $object->supportsImports()) {
            return;
        }

        if (array_key_exists('imports', $values)) {
            $value = $values['imports'];
            unset($values['imports']);
            $object->clearImportedObjects();
            $object->imports()->set($value);
        }

        $el = $this->getElement('imports');
        if ($el) {
            $el->setMultiOptions($this->enumAllowedTemplates());
            $el->setValue($object->imports()->listImportNames());
        }
    }

    protected function handleRanges($object, & $values)
    {
        if (! $object->supportsRanges()) {
            return;
        }

        $key = 'ranges';
        $object = $this->object();

        /* Sample:

        array(
            'monday'  => 'eins',
            'tuesday' => '00:00-24:00',
            'sunday'  => 'zwei',
        );

        */
        if (array_key_exists($key, $values)) {
            $object->ranges()->set($values[$key]);
            unset($values[$key]);
        }

        foreach ($object->ranges()->getRanges() as $key => $value) {
            $this->addRange($key, $value);
        }

        /*
        // TODO implement when new logic is there
        $this->addElement('note', '_newrange_hint', array('label' => 'New range'));
        $this->addElement('text', '_newrange_name', array(
            'label' => 'Name'
        ));
        $this->addElement('text', '_newrange_value', array(
            'label' => 'Value'
        ));
        */
    }

    protected function addToFieldsDisplayGroup($elements)
    {
        return $this->addElementsToGroup(
            $elements,
            'custom_fields',
            50,
            $this->translate('Custom properties')
        );
    }

    protected function addToCheckExecutionDisplayGroup($elements)
    {
        return $this->addElementsToGroup(
            $elements,
            'check_execution',
            60,
            $this->translate('Check execution')
        );
    }

    protected function addToCommandFieldsDisplayGroup($elements)
    {
        return $this->addElementsToGroup(
            $elements,
            'command_fields',
            55,
            $this->translate('Command-specific custom vars')
        );
    }

    protected function addElementsToGroup($elements, $group, $order, $legend = null)
    {
        if (! is_array($elements)) {
            $elements = array($elements);
        }
        foreach ($elements as $k => $v) {
            if (is_string($v)) {
                $elements[$k] = $this->getElement($v);
            }
        }

        if (! array_key_exists($group, $this->displayGroups)) {
            $this->addDisplayGroup($elements, $group, array(
                'decorators' => array(
                    'FormElements',
                    'Fieldset',
                ),
                'order'  => $order,
                'legend' => $legend ?: $group,
            ));
            $this->displayGroups[$group] = $this->getDisplayGroup($group);
        } else {
            $this->displayGroups[$group]->addElements($elements);
        }

        return $this->displayGroups[$group];
    }

    protected function handleGroups($object, & $values)
    {
        if (! $object->supportsGroups()) {
            return;
        }

        if (array_key_exists('groups', $values)) {
            $value = $values['groups'];
            unset($values['groups']);

            // TODO: Drop this once we have arrays everwhere
            if (is_string($value)) {
                $value =  preg_split('/\s*,\s*/', $value, -1, PREG_SPLIT_NO_EMPTY);
            }

            $object->groups()->set($value);
        }
    }

    protected function handleProperties($object, & $values)
    {
        if ($this->hasBeenSent()) {
            $object->setProperties($values);
        }

        $props = $object->getProperties();

        if (! $object instanceof IcingaObject) {
            $this->setDefaults($props);
            return $this;
        }

        if (! $object->supportsImports()) {
            $this->setDefaults($props);
            return;
        }

        $inherited = $object->getInheritedProperties();
        $origins   = $object->getOriginsProperties();

        foreach ($props as $k => $v) {
            if ($k !== 'object_name' && property_exists($inherited, $k)) {
                $this->setElementValue($k, $v, $inherited->$k, $origins->$k);
                if ($el = $this->getElement($k)) {
                    $el->setRequired(false);
                }
            } else {
                $this->setElementValue($k, $v);
            }
        }
    }

    protected function handleCustomVars($object, & $values)
    {
        if (! $object->supportsCustomVars()) {
            return;
        }

        $fields   = $object->getResolvedFields();
        $inherits = $object->getInheritedVars();
        $origins  = $object->getOriginsVars();
        if ($object->hasCheckCommand()) {
            $checkCommand = $object->getCheckCommand();
            $checkFields = $checkCommand->getResolvedFields();
            $checkVars   = $checkCommand->getResolvedVars();
        } else {
            $checkFields = (object) array();
        }

        if ($this->hasBeenSent()) {
            $vars = array();
            $handled = array();
            $newvar = array(
                'type'  => 'string',
                'name'  => null,
                'value' => null,
            );

            foreach ($values as $key => $value) {

                if (substr($key, 0, 4) === 'var_') {
                    $mykey = substr($key, 4);
                    if (property_exists($fields, $mykey) && $fields->$mykey->format === 'json') {
                        $value = json_decode($value);
                    }

                    if (property_exists($checkFields, $mykey) && $checkFields->$mykey->format === 'json') {
                        $value = json_decode($value);
                    }

                    $vars[$mykey] = $value;
                    $handled[$key] = true;
                }
/*
                if (substr($key, 0, 8) === '_newvar_') {
                    $newvar[substr($key, 8)] = $value;
                    $handled[$key] = true;
                }
*/
            }

            foreach ($vars as $k => $v) {
                if ($v === '' || $v === null) {
                    unset($object->vars()->$k);
                } else {
                    $object->vars()->$k = $v;
                }
            }

            if ($newvar['name'] && $newvar['value']) {
                $object->vars()->{$newvar['name']} = $newvar['value'];
            }

            foreach ($handled as $key) {
                unset($values[$key]);
            }
        }

        $vars = $object->getVars();

        foreach ($fields as $field) {
            $varname = $field->varname;

            // Get value from the related varname if set:
            if (property_exists($vars, $varname)) {
                $value = $vars->$varname;
            } else {
                $value = null;
            }

            if (property_exists($inherits, $varname)) {
                $inheritedValue = $inherits->$varname;
                $inheritFrom = $origins->$varname;
                if ($inheritFrom === $object->object_name) {
                    $inherited = false;
                } else {
                    $inherited = true;
                }
            } else {
                $inheritedValue = null;
                $inheritFrom = false;
                $inherited = false;
            }

            $this->addField($field, $value, $inheritedValue, $inheritFrom);
        }



        foreach ($checkFields as $field) {
            $varname = $field->varname;
            if (property_exists($vars, $varname)) {
                $value = $vars->$varname;
            } else {
                $value = null;
            }
            if (property_exists($checkVars, $varname)) {
                $inheritedValue = $checkVars->$varname;
                $inheritFrom = $this->translate('check command');
            } else {
                $inheritedValue = null;
                $inheritFrom = false;
            }
            $this->addCommandField($field, $value, $inheritedValue, $inheritFrom);
            if ($inheritedValue !== null) {
                $this->getElement('var_' . $field->varname)->setRequired(false);
            }
        }




        // Additional vars
        foreach ($vars as $key => $value) {
            // Did we already create a field for this var? Then skip it:
            if (array_key_exists($key, $fields)) {
                continue;
            }
            if (array_key_exists($key, $checkFields)) {
                continue;
            }

            // TODO: handle structured vars
            if (! is_string($value)) continue;

            // Show inheritance information in case we inherited this var:
            if (isset($inherited->$key)) {
                $this->addCustomVar($key, $value, $inherited->$key, $origins->$key);            
            } else {
                $this->addCustomVar($key, $value);            
            }

        }

        if (/* TODO: add free var */false && $object->isTemplate()) {
            $this->addElement('text', '_newvar_name', array(
                'label' => 'Add var'
            ));
            $this->addElement('text', '_newvar_value', array(
                'label' => 'Value'
            ));
            $this->addElement('select', '_newvar_format', array(
                'label'        => 'Type',
                'multiOptions' => array('string' => $this->translate('String'))
            ));
            $this->addToFieldsDisplayGroup(
                array(
                    '_newvar_name',
                    '_newvar_value',
                    '_newvar_format',
                )
            );
        }
    }

    public function setObjectType($type)
    {
        $this->objectType = $type;
        return $this;
    }

    protected function setButtons()
    {
        if ($this->object === null || ! $this->object->hasBeenLoadedFromDb()) {
            $this->setSubmitLabel(
                $this->translate('Add')
            );
        } else {
            $this->setSubmitLabel(
                $this->translate('Store')
            );
            $this->addDeleteButton();
        }
    }

    protected function addField($field, $value = null, $inherited = null, $inheritedFrom = null)
    {
        $datafield = DirectorDatafield::load($field->datafield_id, $this->getDb());
        $name = 'var_' . $datafield->varname;
        $className = $datafield->datatype;

        if (! class_exists($className)) {
            $this->addElement('text', $name, array('disabled' => 'disabled'));
            $el = $this->getElement($name);
            $el->addError(sprintf('Form element could not be created, %s is missing', $className));
            $this->addToFieldsDisplayGroup($el);
            return $el;
        }

        $datatype = new $className;
        $datatype->setSettings($datafield->getSettings());
        $el = $datatype->getFormElement($name, $this);

        $el->setLabel($datafield->caption);
        $el->setDescription($datafield->description);

        if ($field->is_required === 'y' && ! $this->isTemplate() && $inherited === null) {
            $el->setRequired(true);
        }

        $this->addElement($el);
        $this->setElementValue($name, $value, $inherited, $inheritedFrom);
        $this->addToFieldsDisplayGroup($el);

        return $el;
    }

    protected function addCommandField($field, $value = null, $inherited = null, $inheritedFrom = null)
    {
        $datafield = DirectorDatafield::load($field->datafield_id, $this->getDb());
        $name = 'var_' . $datafield->varname;
        $className = $datafield->datatype;

        if (! class_exists($className)) {
            $this->addElement('text', $name, array('disabled' => 'disabled'));
            $el = $this->getElement($name);
            $el->addError(sprintf('Form element could not be created, %s is missing', $className));
            return $el;
        }

        $datatype = new $className;
        $datatype->setSettings($datafield->getSettings());
        $el = $datatype->getFormElement($name, $this);

        $el->setLabel($datafield->caption);
        $el->setDescription($datafield->description);

        if ($field->is_required === 'y' && ! $this->isTemplate() && $inherited === null) {
            $el->setRequired(true);
        }

        $this->addElement($el);
        $this->setElementValue($name, $value, $inherited, $inheritedFrom);
        $this->addToCommandFieldsDisplayGroup($el);

        return $el;
    }

    protected function setElementValue($name, $value = null, $inherited = null, $inheritedFrom = null)
    {
        $el = $this->getElement($name);
        if (! $el) {
            return;
        }

        if ($value !== null) {
            $el->setValue($value);
        }

        if ($inherited === null || empty($inherited)) {
            return;
        }

        $txtInherited = $this->translate(' (inherited from "%s")');
        if ($el instanceof Zf_Select) {
            $multi = $el->getMultiOptions();
            if (array_key_exists($inherited, $multi)) {
                $multi[null] = $multi[$inherited] . sprintf($txtInherited, $inheritedFrom);
            } else {
                $multi[null] = $this->translate($this->translate('- inherited -'));
            }
            $el->setMultiOptions($multi);
        } else {
            $el->setAttrib('placeholder', $inherited . sprintf($txtInherited, $inheritedFrom));
        }
    }

    public function onSuccess()
    {
        $object = $this->object();
        if ($object->hasBeenModified()) {

            if (! $object->hasBeenLoadedFromDb()) {

                $this->setHttpResponseCode(201);
                if ($object instanceof IcingaObject && $object->hasProperty('object_name')) {
                    $this->setSuccessUrl(
                        'director/' . strtolower($this->getObjectName()),
                        array('name' => $object->object_name)
                    );
                }
            }
            $msg = sprintf(
                $object->hasBeenLoadedFromDb()
                ? $this->translate('The %s has successfully been stored')
                : $this->translate('A new %s has successfully been created'),
                $this->translate($this->getObjectName())
            );
            $object->store($this->db);
        } else {
            if ($this->isApiRequest()) {
                $this->setHttpResponseCode(304);
            }
            $msg = $this->translate('No action taken, object has not been modified');
        }

        $this->redirectOnSuccess($msg);
    }

    protected function addBoolean($key, $options, $default = null)
    {
        $map = array(
            false => 'n',
            true  => 'y',
            'n'   => 'n',
            'y'   => 'y',
        );
        if ($default !== null) {
            $options['multiOptions'] = $this->enumBoolean();
        } else {
            $options['multiOptions'] = $this->optionalEnum($this->enumBoolean());
        }

        $res = $this->addElement('select', $key, $options);

        if ($default !== null) {
            $this->getElement($key)->setValue($map[$default]);
        }

        return $res;
    }

    protected function optionalBoolean($key, $label, $description)
    {
        return $this->addBoolean($key, array(
            'label'       => $label,
            'description' => $description
        ));
    }

    protected function enumBoolean()
    {
        return array(
            'y'  => $this->translate('Yes'),
            'n'  => $this->translate('No'),
        );
    }

    public function hasElement($name)
    {
        return $this->getElement($name) !== null;
    }

    public function getObject()
    {
        return $this->object;
    }

    public function hasObject()
    {
        return $this->object !== null;
    }

    public function setObject(IcingaObject $object)
    {
        $this->object = $object;
        if ($this->db === null) {
            $this->setDb($db);
        }

        return $this;
    }

    protected function getObjectClassname()
    {
        if ($this->className === null) {
            return 'Icinga\\Module\\Director\\Objects\\'
               . substr(join('', array_slice(explode('\\', get_class($this)), -1)), 0, -4);
        }

        return $this->className;
    }

    protected function getObjectname()
    {
        if ($this->objectName === null) {
            return substr(join('', array_slice(explode('\\', get_class($this)), -1)), 6, -4);
        }

        return $this->objectName;
    }

    protected function onRequest()
    {
        $values = array();

        $object = $this->object();

        if ($this->shouldBeDeleted()) {
            $this->deleteObject($object);
        }

        if ($this->hasBeenSent()) {
            $post = $this->getRequest()->getPost();
            foreach ($post as $key => $value) {
                $el = $this->getElement($key);
                if ($el && ! $el->getIgnore()) {
                    $values[$key] = $value;
                }
            }
        }

        if ($object instanceof IcingaObject) {
            if (! $object->hasBeenLoadedFromDb() && $object->hasProperty('object_type')) {
                $object->object_type = $this->objectType;
            }
            $this->handleImports($object, $values);
            $this->handleProperties($object, $values);
            $this->handleCustomVars($object, $post);
            $this->handleGroups($object, $values);
            $this->handleRanges($object, $values);
        } else {
            $this->handleProperties($object, $values);
        }

        /*
        // TODO: something like this could be used to remember unstored changes
        if ($object->hasBeenModified()) {
            $this->addHtmlHint($this->translate('Object has been modified'));
        }
        */
    }

    protected function deleteObject($object)
    {
        $key = $object->getKeyName();
        if ($object instanceof IcingaObject && $object->hasProperty('object_name')) {
            $msg = sprintf(
                '%s "%s" has been removed',
                $this->translate($this->getObjectName()),
                $object->object_name
            );
        } else {
            $msg = sprintf(
                '%s has been removed',
                $this->translate($this->getObjectName())
            );
        }

        if ($object->delete()) {
            // fields? $this->setSuccessUrl($this->getSuccessUrl()->without($key));
            if ($object instanceof IcingaObject && $object->hasProperty('object_name')) {
                $this->setSuccessUrl('director/' . $object->getShortTableName() . 's');
            } else {
                $this->setSuccessUrl($this->getSuccessUrl()->without(
                    array('field_id', 'argument_id')
                ));
            }
        }
        // TODO: show object name and so
        $this->redirectOnSuccess($msg);
    }

    protected function addDeleteButton($label = null)
    {
        if ($label === null) {
            $label = $this->translate('Delete');
        }

        $el = $this->createElement('submit', $label)->setLabel($label)->setDecorators(array('ViewHelper')); //->removeDecorator('Label');

        $this->deleteButtonName = $el->getName();
        $this->addElement($el);

        return $this;
    }

    public function hasDeleteButton()
    {
        return $this->deleteButtonName !== null;
    }

    public function shouldBeDeleted()
    {
        if (! $this->hasDeleteButton()) return false;

        $name = $this->deleteButtonName;
        return $this->getSentValue($name) === $this->getElement($name)->getLabel();
    }

    public function getSentOrObjectValue($name, $default = null)
    {
        if ($this->hasObject()) {
            $value = $this->getSentValue($name);
            if ($value === null) {
                $object = $this->getObject();

                if ($object->hasProperty($name)) {
                    return $object->$name;
                }

                return $default;
            } else {

                return $value;
            }

        } else {
            return $this->getSentValue($name, $default);
        }
    }

    public function loadObject($id)
    {
        $class = $this->getObjectClassname();
        $this->object = $class::load($id, $this->db);

        // TODO: hmmmm...
        if (! is_array($id) && $this->object->getKeyName() === 'id') {
            $this->addHidden('id', $id);
        }

        return $this;
    }

    protected function addCustomVar($key, $value, $inherited = null, $inheritedFrom = null)
    {
        $label = 'vars.' . $key;
        $key = 'var_' . $key;
        $this->addElement('text', $key, array('label' => $label));
        $this->setElementValue($key, $value, $inherited, $inheritedFrom);
        $this->addToFieldsDisplayGroup($key);
    }

    protected function addRange($key, $range)
    {
        $this->addElement('text', 'range_' . $key, array(
            'label' => 'ranges.' . $key,
            'value' => $range->timeperiod_value
        ));
    }

    public function getDb()
    {
        return $this->db;
    }

    public function setDb($db)
    {
        $this->db = $db;
        if ($this->object !== null) {
            $this->object->setConnection($db);
        }

        return $this;
    }

    protected function addZoneElement()
    {
        if ($this->isTemplate()) {
            $zones = $this->db->enumZones();
        } else {
            $zones = $this->db->enumNonglobalZones();
        }

        $this->addElement('select', 'zone_id', array(
            'label' => $this->translate('Cluster Zone'),
            'description'  => $this->translate('Icinga cluster zone'),
            'multiOptions' => $this->optionalEnum($zones)
        ));

        return $this;
    }

    protected function addImportsElement()
    {
        $this->addElement('multiselect', 'imports', array(
            'label'        => $this->translate('Imports'),
            'description'  => $this->translate('Importable templates, choose one or more of them (CTRL/SHIFT click)'),
            'multiOptions' => $this->enumAllowedTemplates(),
            'size'         => 8,
            'class'        => 'autosubmit'
        ));

        return $this;
    }

    protected function addCheckCommandElements()
    {
        $this->addElement('select', 'check_command_id', array(
            'label' => $this->translate('Check command'),
            'description'  => $this->translate('Check command definition'),
            'multiOptions' => $this->optionalEnum($this->db->enumCheckCommands()),
            'class'        => 'autosubmit', // This influences fields
        ));
        $this->addToCheckExecutionDisplayGroup('check_command_id');
    }

    protected function addCheckExecutionElements()
    {

        $this->addElement('text', 'check_interval', array(
            'label' => $this->translate('Check interval'),
            'description' => $this->translate('Your regular check interval')
        ));

        $this->addElement('text', 'retry_interval', array(
            'label' => $this->translate('Retry interval'),
            'description' => $this->translate('Retry interval, will be applied after a state change unless the next hard state is reached')
        ));

        $this->optionalBoolean(
            'enable_active_checks', 
            $this->translate('Execute active checks'),
            $this->translate('Whether to actively check this object')
        );

        $this->optionalBoolean(
            'enable_passive_checks', 
            $this->translate('Accept passive checks'),
            $this->translate('Whether to accept passive check results for this object')
        );

        $this->optionalBoolean(
            'enable_notifications',
            $this->translate('Send notifications'),
            $this->translate('Whether to send notifications for this object')
        );

        $this->optionalBoolean(
            'enable_event_handler',
            $this->translate('Enable event handler'),
            $this->translate('Whether to enable event handlers this object')
        );

        $this->optionalBoolean(
            'enable_perfdata',
            $this->translate('Process performance data'),
            $this->translate('Whether to process performance data provided by this object')
        );

        $this->optionalBoolean(
            'volatile',
            $this->translate('Volatile'),
            $this->translate('Whether this check is volatile.')
        );

        $elements = array(
            'check_interval',
            'retry_interval',
            'enable_active_checks',
            'enable_passive_checks',
            'enable_notifications',
            'enable_event_handler',
            'enable_perfdata',
            'volatile'
        );
        $this->addToCheckExecutionDisplayGroup($elements);

        return $this;
    }

    protected function enumAllowedTemplates()
    {
        $object = $this->object();
        $tpl = $this->db->enumIcingaTemplates($object->getShortTableName());
        if (empty($tpl)) {
            return array();
        }

        $tpl = array_combine($tpl, $tpl);
        $id = $object->object_name;

        if (array_key_exists($id, $tpl)) {
            unset($tpl[$id]);
        }
        return $tpl;
    }

    private function dummyForTranslation()
    {
        $this->translate('Host');
        $this->translate('Service');
        $this->translate('Zone');
        $this->translate('Command');
        $this->translate('User');
        // ... TBC
    }
}
