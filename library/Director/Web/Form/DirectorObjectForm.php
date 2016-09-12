<?php

namespace Icinga\Module\Director\Web\Form;

use Exception;
use Icinga\Module\Director\IcingaConfig\StateFilterSet;
use Icinga\Module\Director\IcingaConfig\TypeFilterSet;
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

    protected $fieldsDisplayGroup;

    protected $displayGroups = array();

    protected $resolvedImports = false;

    protected $listUrl;

    private $allowsExperimental;

    private $api;

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

    protected function assertResolvedImports()
    {
        if ($this->resolvedImports) {
            return $this;
        }

        $this->resolvedImports = true;
        $object = $this->object;

        if (! $object instanceof IcingaObject) {
            return $this;
        }
        if (! $object->supportsImports()) {
            return $this;
        }
        if ($this->hasBeenSent()) {
            if ($el = $this->getElement('imports')) {
                $this->populate($this->getRequest()->getPost());
                $object->imports = $el->getValue();
            }
        }

        $object->resolveUnresolvedRelatedProperties();
        return $this;
    }

    protected function isObject()
    {
        return $this->getSentOrObjectValue('object_type') === 'object';
    }

    protected function isTemplate()
    {
        return $this->getSentOrObjectValue('object_type') === 'template';
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
        $this->addElement('simpleNote', '_newrange_hint', array('label' => 'New range'));
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

        // These are optional elements, they might exist or not. We still want
        // to see exception for other ones
        $skipLegally = array('check_period_id');

        $skip = array();
        foreach ($elements as $k => $v) {
            if (is_string($v)) {
                $el = $this->getElement($v);
                if (!$el && in_array($v, $skipLegally)) {
                    $skip[] = $k;
                    continue;
                }

                $elements[$k] = $el;
            }
        }

        foreach ($skip as $k) {
            unset($elements[$k]);
        }

        if (! array_key_exists($group, $this->displayGroups)) {
            $this->addDisplayGroup($elements, $group, array(
                'decorators' => array(
                    'FormElements',
                    array('HtmlTag', array('tag' => 'dl')),
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

    protected function handleProperties($object, & $values)
    {
        if ($this->hasBeenSent()) {
            foreach ($values as $key => $value) {
                try {
                    $object->set($key, $value);
                    if ($object instanceof IcingaObject) {
                        $object->resolveUnresolvedRelatedProperties();
                    }

                } catch (Exception $e) {
                    $this->getElement($key)->addError($e->getMessage());
                }
            }
        }

        if ($object instanceof IcingaObject) {
            $props = (array) $object->toPlainObject(
                false,
                false,
                null,
                false // Do not resolve IDs
            );
        } else {
            $props = $object->getProperties();
            unset($props['vars']);
        }

        foreach ($props as $k => $v) {
            if (is_bool($v)) {
                $props[$k] = $v ? 'y' : 'n';
            }
        }

        $this->setDefaults($props);

        if (! $object instanceof IcingaObject) {
            return $this;
        }

        if ($object->supportsImports()) {
            $inherited = $object->getInheritedProperties();
            $origins   = $object->getOriginsProperties();
        } else {
            $inherited = (object) array();
            $origins   = (object) array();
        }

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
                    if (substr($key, -6) === '___ADD') {
                        continue;
                    }

                    $mykey = substr($key, 4);

                    // Get value through form element.
                    // TODO: reorder the related code. Create elements once
                    foreach (array($fields, $checkFields) as $fieldSet) {
                        if (property_exists($fieldSet, $mykey)) {
                            $field = $fieldSet->$mykey;
                            $datafield = DirectorDatafield::load($field->datafield_id, $this->getDb());
                            $el = $datafield->getFormElement($this);
                            $value = $el->setValue($value)->getValue();
                        }
                    }

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

            // Command vars are overridden at object level:
            if (property_exists($inherits, $varname)) {
                $inheritedValue = $inherits->$varname;
                $inheritFrom = $origins->$varname;
                if ($inheritFrom === $object->object_name) {
                    $inherited = false;
                } else {
                    $inherited = true;
                }
            }

            $this->addCommandField($field, $value, $inheritedValue, $inheritFrom);
            if ($inheritedValue !== null) {
                $this->getElement('var_' . $field->varname)->setRequired(false);
            }
        }

        // TODO Define whether admins are allowed to set those
        /*
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
            if (! is_string($value)) {
                continue;
            }

            // Show inheritance information in case we inherited this var:
            if (isset($inherited->$key)) {
                $this->addCustomVar($key, $value, $inherited->$key, $origins->$key);
            } else {
                $this->addCustomVar($key, $value);
            }

        }
        */

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

    protected function isNew()
    {
        return $this->object === null || ! $this->object->hasBeenLoadedFromDb();
    }

    protected function setButtons()
    {
        if ($this->isNew()) {
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

    protected function groupMainProperties()
    {
        $elements = array(
            'object_type',
            'imports',
            'object_name',
            'display_name',
            'host_id',
            'address',
            'address6',
            'groups',
            'users',
            'user_groups',
            'apply_to',
            'command_id', // Notification
            'notification_interval',
            'period_id',
            'times_begin',
            'times_end',
            'email',
            'pager',
            'enable_notifications',
            'create_live',
            'disabled',
            'disable_checks', //Dependencies
            'disable_notifications',
            'ignore_soft_states',
        );

        $this->addDisplayGroup($elements, 'object_definition', array(
            'decorators' => array(
                'FormElements',
                array('HtmlTag', array('tag' => 'dl')),
                'Fieldset',
            ),
            'order' => 20,
            'legend' => $this->translate('Main properties')
        ));

        return $this;
    }

    // TODO: unify addField and addCommandField. Do they need to differ?
    protected function addField($field, $value = null, $inherited = null, $inheritedFrom = null)
    {
        $datafield = DirectorDatafield::load($field->datafield_id, $this->getDb());
        $el = $datafield->getFormElement($this);

        if ($field->is_required === 'y' && ! $this->isTemplate() && $inherited === null) {
            $el->setRequired(true);
        }

        $this->addElement($el);
        $this->addToFieldsDisplayGroup($el);
        if (! $el->hasErrors()) {
            $this->setElementValue($el->getName(), $value, $inherited, $inheritedFrom);
        }

        return $el;
    }

    protected function addCommandField($field, $value = null, $inherited = null, $inheritedFrom = null)
    {
        $datafield = DirectorDatafield::load($field->datafield_id, $this->getDb());
        $el = $datafield->getFormElement($this);

        if ($field->is_required === 'y' && ! $this->isTemplate() && $inherited === null) {
            $el->setRequired(true);
        }

        $this->addElement($el);
        $this->addToCommandFieldsDisplayGroup($el);
        if (! $el->hasErrors()) {
            $this->setElementValue($el->getName(), $value, $inherited, $inheritedFrom);
        }

        return $el;
    }

    protected function setSentValue($name, $value)
    {
        if ($this->hasBeenSent()) {
            $request = $this->getRequest();
            if ($value !== null && $request->isPost() && $request->getPost($name) !== null) {
                $request->setPost($name, $value);
            }
        }

        return $this->setElementValue($name, $value);
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

        $txtInherited = ' ' . $this->translate(' (inherited from "%s")');
        if ($el instanceof Zf_Select) {
            $multi = $el->getMultiOptions();
            if (is_bool($inherited)) {
                $inherited = $inherited ? 'y' : 'n';
            }
            if (array_key_exists($inherited, $multi)) {
                $multi[null] = $multi[$inherited] . sprintf($txtInherited, $inheritedFrom);
            } else {
                $multi[null] = $this->translate($this->translate('- inherited -'));
            }
            $el->setMultiOptions($multi);
        } else {
            if (is_string($inherited)) {
                $el->setAttrib('placeholder', $inherited . sprintf($txtInherited, $inheritedFrom));
            }
        }
    }

    public function setListUrl($url)
    {
        $this->listUrl = $url;
        return $this;
    }

    public function onSuccess()
    {
        $object = $this->object();
        if ($object->hasBeenModified()) {

            if (! $object->hasBeenLoadedFromDb()) {

                $this->setHttpResponseCode(201);
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
        if ($object instanceof IcingaObject) {
            $this->setSuccessUrl(
                'director/' . strtolower($this->getObjectName()),
                $object->getUrlParams()
            );
        }
        $this->beforeSuccessfulRedirect();
        $this->redirectOnSuccess($msg);
    }

    protected function beforeSuccessfulRedirect()
    {
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
            $className = substr(strrchr(get_class($this), '\\'), 1);
            if (substr($className, 0, 6) === 'Icinga') {
                return substr($className, 6, -4);
            } else {
                return substr($className, 0, -4);
            }
        }

        return $this->objectName;
    }

    protected function removeFromSet(& $set, $key)
    {
        unset($set[$key]);
        sort($set);
    }

    protected function moveUpInSet(& $set, $key)
    {
        list($set[$key - 1], $set[$key]) = array($set[$key], $set[$key - 1]);
    }

    protected function moveDownInSet(& $set, $key)
    {
        list($set[$key + 1], $set[$key]) = array($set[$key], $set[$key + 1]);
    }

    protected function beforeSetup()
    {
        if (!$this->hasBeenSent()) {
            return;
        }

        $post = $values = $this->getRequest()->getPost();

        foreach ($post as $key => $value) {

            if (preg_match('/^(.+?)_(\d+)__(MOVE_DOWN|MOVE_UP|REMOVE)$/', $key, $m)) {
                $values[$m[1]] = array_filter($values[$m[1]], 'strlen');
                switch ($m[3]) {
                    case 'MOVE_UP':
                        $this->moveUpInSet($values[$m[1]], $m[2]);
                        break;
                    case 'MOVE_DOWN':
                        $this->moveDownInSet($values[$m[1]], $m[2]);
                        break;
                    case 'REMOVE':
                        $this->removeFromSet($values[$m[1]], $m[2]);
                        break;
                }

                $this->getRequest()->setPost($m[1], $values[$m[1]]);
            }
        }
    }

    protected function onRequest()
    {
        $values = array();

        $object = $this->object();
        if ($this->hasBeenSent()) {

            if ($this->shouldBeDeleted()) {
                $this->deleteObject($object);
            }

            $post = $this->getRequest()->getPost();
            // ?? $this->populate($post);
            if (array_key_exists('assignlist', $post)) {
                $object->assignments()->setFormValues($post['assignlist']);
            }

            foreach ($post as $key => $value) {
                $el = $this->getElement($key);
                if ($el && ! $el->getIgnore()) {
                    $values[$key] = $el->setValue($value)->getValue();
                }
            }
        }

        if ($object instanceof IcingaObject) {
            if ($object->supportsAssignments()) {
                $this->setElementValue('assignlist', $object->assignments()->getFormValues());
            }

            $this->handleProperties($object, $values);
            $this->handleCustomVars($object, $post);
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

        if ($this->listUrl) {
            $url = $this->listUrl;
        } elseif ($object instanceof IcingaObject && $object->hasProperty('object_name')) {
            $url = $object->getOnDeleteUrl();
        } else {
            $url = $this->getSuccessUrl()->without(
                array('field_id', 'argument_id', 'range', 'range_type')
            );
        }

        if ($object->delete()) {
            $this->setSuccessUrl($url);
        }
        // TODO: show object name and so
        $this->redirectOnSuccess($msg);
    }

    protected function addDeleteButton($label = null)
    {
        $object = $this->object;

        if ($label === null) {
            $label = $this->translate('Delete');
        }

        $el = $this->createElement('submit', $label)
            ->setLabel($label)
            ->setDecorators(array('ViewHelper'));
            //->removeDecorator('Label');

        $this->deleteButtonName = $el->getName();

        if ($object instanceof IcingaObject && $object->isTemplate()) {
            if ($cnt = $object->countDirectDescendants()) {
                $el->setAttrib('disabled', 'disabled');
                $el->setAttrib(
                    'title',
                    sprintf(
                        $this->translate('This template is still in use by %d other objects'),
                        $cnt
                    )
                );
            }
        }

        $this->addElement($el);

        return $this;
    }

    public function hasDeleteButton()
    {
        return $this->deleteButtonName !== null;
    }

    public function shouldBeDeleted()
    {
        if (! $this->hasDeleteButton()) {
            return false;
        }

        $name = $this->deleteButtonName;
        return $this->getSentValue($name) === $this->getElement($name)->getLabel();
    }

    public function getSentOrResolvedObjectValue($name, $default = null)
    {
        return $this->getSentOrObjectValue($name, $default, true);
    }

    public function getSentOrObjectValue($name, $default = null, $resolved = false)
    {
        // TODO: check whether getSentValue is still needed since element->getValue
        //       is in place (currently for form element default values only)

        if (!$this->hasObject()) {
            if ($this->hasBeenSent()) {

                return $this->getSentValue($name, $default);
            } else {
                if ($this->valueIsEmpty($val = $this->getValue($name))) {
                    return $default;
                } else {
                    return $val;
                }
            }
        }

        if ($this->hasBeenSent()) {
            if (!$this->valueIsEmpty($value = $this->getSentValue($name))) {
                return $value;
            }
        }

        $object = $this->getObject();

        if ($object->hasProperty($name)) {
            if ($resolved && $object->supportsImports()) {
                $this->assertResolvedImports();
                $objectProperty = $object->getResolvedProperty($name);
            } else {
                $objectProperty = $object->$name;
            }
        } else {
            $objectProperty = null;
        }

        if ($objectProperty !== null) {
            return $objectProperty;
        }

        if (($el = $this->getElement($name)) && !$this->valueIsEmpty($val = $el->getValue())) {
            return $val;
        }

        return $default;
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
            'value' => $range->range_value
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

    public function optionallyAddFromEnum($enum)
    {
        return array(
            null => $this->translate('- click to add more -')
        ) + $enum;
    }

    protected function addObjectTypeElement()
    {
        if (!$this->isNew()) {
            return;
        }

        $object = $this->object();

        if ($object->supportsImports()) {
            $templates = $this->enumAllowedTemplates();

            // TODO: getObjectname is a confusing method name
            if (empty($templates) && $this->getObjectname() !== 'Command') {
                $types = array('template' => $this->translate('Template'));
            } else {
                $types = array(
                    'object'   => $this->translate('Object'),
                    'template' => $this->translate('Template'),
                );
            }
        } else {
             $types = array('object' => $this->translate('Object'));
        }

        if ($this->object()->supportsApplyRules()) {
            $types['apply'] = $this->translate('Apply rule');
        }

        $this->addElement('select', 'object_type', array(
            'label' => $this->translate('Object type'),
            'description'  => $this->translate(
                'What kind of object this should be. Templates allow full access'
                . ' to any property, they are your building blocks for "real" objects.'
                . ' External objects should usually not be manually created or modified.'
                . ' They allow you to work with objects locally defined on your Icinga nodes,'
                . ' while not rendering and deploying them with the Director. Apply rules allow'
                . ' to assign services, notifications and groups to other objects.'
            ),
            'required'     => true,
            'multiOptions' => $this->optionalEnum($types),
            'class'        => 'autosubmit'
        ));

        return $this;
    }

    protected function hasObjectType()
    {
        if (!$this->object()->hasProperty('object_type')) {
            return false;
        }

        return ! $this->valueIsEmpty($this->getSentOrObjectValue('object_type'));
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
            'description'  => $this->translate(
                'Icinga cluster zone. Allows to manually override Directors decisions'
                . ' of where to deploy your config to. You should consider not doing so'
                . ' unless you gained deep understanding of how an Icinga Cluster stack'
                . ' works'
            ),
            'multiOptions' => $this->optionalEnum($zones)
        ));

        return $this;
    }

    protected function addImportsElement()
    {
        $enum = $this->enumAllowedTemplates();
        if (empty($enum)) {
            return $this;
        }

        $this->addElement('extensibleSet', 'imports', array(
            'label'        => $this->translate('Imports'),
            'description'  => $this->translate(
                'Importable templates, add as many as you want. Please note that order'
                . ' matters when importing properties from multiple templates: last one'
                . ' wins'
            ),
            'required'     => !$this->isTemplate(),
            'multiOptions' => $this->optionallyAddFromEnum($enum),
            'sorted'       => true,
            'class'        => 'autosubmit'
        ));

        return $this;
    }

    protected function addDisabledElement()
    {
        if ($this->isTemplate()) {
            return $this;
        }

        $this->addBoolean(
            'disabled',
            array(
                'label'       => $this->translate('Disabled'),
                'description' => $this->translate('Disabled objects will not be deployed')
            ),
            'n'
        );

        return $this;
    }

    protected function addGroupDisplayNameElement()
    {
        $this->addElement('text', 'display_name', array(
            'label' => $this->translate('Display Name'),
            'description' => $this->translate(
                'An alternative display name for this group. If you wonder how this'
                . ' could be helpful just leave it blank'
            )
        ));

        return $this;
    }

    protected function addCheckCommandElements()
    {
        if (! $this->isTemplate()) {
            return $this;
        }

        $this->addElement('select', 'check_command_id', array(
            'label' => $this->translate('Check command'),
            'description'  => $this->translate('Check command definition'),
            'multiOptions' => $this->optionalEnum($this->db->enumCheckCommands()),
            'class'        => 'autosubmit', // This influences fields
        ));
        $this->addToCheckExecutionDisplayGroup('check_command_id');

        $eventCommands = $this->db->enumEventCommands();

        if (! empty($eventCommands)) {
            $this->addElement('select', 'event_command_id', array(
                'label' => $this->translate('Event command'),
                'description'  => $this->translate('Event command definition'),
                'multiOptions' => $this->optionalEnum($eventCommands),
                'class'        => 'autosubmit',
            ));
            $this->addToCheckExecutionDisplayGroup('event_command_id');
        }

        return $this;
    }

    protected function addCheckExecutionElements()
    {
        if (! $this->isTemplate()) {
            return $this;
        }

        $this->addElement(
            'text',
            'check_interval',
            array(
                'label' => $this->translate('Check interval'),
                'description' => $this->translate('Your regular check interval')
            )
        );

        $this->addElement(
            'text',
            'retry_interval',
            array(
                'label' => $this->translate('Retry interval'),
                'description' => $this->translate(
                    'Retry interval, will be applied after a state change unless the next hard state is reached'
                )
            )
        );

        $this->addElement(
            'text',
            'max_check_attempts',
            array(
                'label' => $this->translate('Max check attempts'),
                'description' => $this->translate(
                    'Defines after how many check attempts a new hard state is reached'
                )
            )
        );

        $periods = $this->db->enumTimeperiods();
        if (!empty($periods)) {

            $this->addElement(
                'select',
                'check_period_id',
                array(
                    'label' => $this->translate('Check period'),
                    'description' => $this->translate(
                        'The name of a time period which determines when this'
                        . ' object should be monitored. Not limited by default.'
                    ),
                    'multiOptions' => $this->optionalEnum($periods),
                )
            );
        }

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
            'max_check_attempts',
            'check_period_id',
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

        $id = $object->id;

        if (array_key_exists($id, $tpl)) {
            unset($tpl[$id]);
        }

        if (empty($tpl)) {
            return array();
        }

        $tpl = array_combine($tpl, $tpl);
        return $tpl;
    }

    protected function addExtraInfoElements()
    {
        $this->addElement('textarea', 'notes', array(
            'label'   => $this->translate('Notes'),
            'description' => $this->translate(
                'Additional notes for this object'
            ),
            'rows'    => 2,
            'columns' => 60,
        ));

        $this->addElement('text', 'notes_url', array(
            'label'   => $this->translate('Notes URL'),
            'description' => $this->translate(
                'An URL pointing to additional notes for this object'
            ),
        ));

        $this->addElement('text', 'action_url', array(
            'label'   => $this->translate('Action URL'),
            'description' => $this->translate(
                'An URL leading to additional actions for this object. Often used'
                . ' with Icinga Classic, rarely with Icinga Web 2 as it provides'
                . ' far better possibilities to integrate addons'
            ),
        ));

        $this->addElement('text', 'icon_image', array(
            'label'   => $this->translate('Icon image'),
            'description' => $this->translate(
                'An URL pointing to an icon for this object. Try "tux.png" for icons'
                . ' relative to public/img/icons or "cloud" (no extension) for items'
                . ' from the Icinga icon font'
            ),
        ));

        $this->addElement('text', 'icon_image_alt', array(
            'label'   => $this->translate('Icon image alt'),
            'description' => $this->translate(
                'Alternative text to be shown in case above icon is missing'
            ),
        ));

        $elements = array(
            'notes',
            'notes_url',
            'action_url',
            'icon_image',
            'icon_image_alt',
        );

        $this->addDisplayGroup($elements, 'extrainfo', array(
            'decorators' => array(
                'FormElements',
                array('HtmlTag', array('tag' => 'dl')),
                'Fieldset',
            ),
            'order'  => 75,
            'legend' => $this->translate('Additional properties')
        ));

        return $this;
    }

    protected function addEventFilterElements($elements = array('states','types'))
    {
        if (in_array('states', $elements)) {
            $this->addElement('extensibleSet', 'states', array(
                'label' => $this->translate('States'),
                'multiOptions' => $this->optionallyAddFromEnum($this->enumStates()),
                'description'  => $this->translate(
                    'The host/service states you want to get notifications for'
                ),
            ));
        }

        if (in_array('types', $elements)) {
            $this->addElement('extensibleSet', 'types', array(
                'label' => $this->translate('Transition types'),
                'multiOptions' => $this->optionallyAddFromEnum($this->enumTypes()),
                'description'  => $this->translate(
                    'The state transition types you want to get notifications for'
                ),
            ));
        }

        $this->addDisplayGroup($elements, 'event_filters', array(
            'decorators' => array(
                'FormElements',
                array('HtmlTag', array('tag' => 'dl')),
                'Fieldset',
            ),
            'order' =>70,
            'legend' => $this->translate('State and transition type filters')
        ));

        return $this;
    }

    protected function allowsExperimental()
    {
        // NO, it is NOT a good idea to use this. You'll break your monitoring
        // and nobody will help you.
        if ($this->allowsExperimental === null) {
            $this->allowsExperimental = $this->db->settings()->get(
                'experimental_features'
            ) === 'allow';
        }

        return $this->allowsExperimental;
    }

    protected function enumStates()
    {
        $set = new StateFilterSet();
        return $set->enumAllowedValues();
    }

    protected function enumTypes()
    {
        $set = new TypeFilterSet();
        return $set->enumAllowedValues();
    }

    public function setApi($api)
    {
        $this->api = $api;
        return $this;
    }

    protected function api()
    {
        return $this->api;
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
