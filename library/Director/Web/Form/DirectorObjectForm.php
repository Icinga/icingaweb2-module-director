<?php

namespace Icinga\Module\Director\Web\Form;

use Exception;
use Icinga\Authentication\Auth;
use Icinga\Module\Director\Db;
use Icinga\Module\Director\Data\Db\DbObject;
use Icinga\Module\Director\Data\Db\DbObjectWithSettings;
use Icinga\Module\Director\Exception\NestingError;
use Icinga\Module\Director\IcingaConfig\StateFilterSet;
use Icinga\Module\Director\IcingaConfig\TypeFilterSet;
use Icinga\Module\Director\Objects\IcingaTemplateChoice;
use Icinga\Module\Director\Objects\IcingaObject;
use Icinga\Module\Director\Util;
use Zend_Form_Element as ZfElement;
use Zend_Form_Element_Select as ZfSelect;

abstract class DirectorObjectForm extends DirectorForm
{
    /** @var IcingaObject */
    protected $object;

    protected $objectName;

    protected $className;

    protected $deleteButtonName;

    protected $fieldsDisplayGroup;

    protected $displayGroups = array();

    protected $resolvedImports;

    protected $listUrl;

    /** @var Auth */
    private $auth;

    private $choiceElements = [];

    protected $preferredObjectType;

    /** @var IcingaObjectFieldLoader */
    protected $fieldLoader;

    private $allowsExperimental;

    private $presetImports;

    private $earlyProperties = array(
        // 'imports',
        'check_command',
        'check_command_id',
        'has_agent',
        'command',
        'command_id',
        'event_command',
        'event_command_id',
    );

    public function setPreferredObjectType($type)
    {
        $this->preferredObjectType = $type;
        return $this;
    }

    public function setAuth(Auth $auth)
    {
        $this->auth = $auth;
        return $this;
    }

    public function getAuth()
    {
        if ($this->auth === null) {
            $this->auth = Auth::getInstance();
        }
        return $this->auth;
    }

    public function presetImports($imports)
    {
        if (! empty($imports)) {
            if (is_array($imports)) {
                $this->presetImports = $imports;
            } else {
                $this->presetImports = array($imports);
            }
        }

        return $this;
    }

    /**
     * @return DbObject|DbObjectWithSettings|IcingaObject
     */
    protected function object()
    {
        if ($this->object === null) {
            $values = $this->getValues();
            /** @var DbObject|IcingaObject $class */
            $class = $this->getObjectClassname();
            if ($this->preferredObjectType) {
                $values['object_type'] = $this->preferredObjectType;
            }
            if ($this->presetImports) {
                $values['imports'] = $this->presetImports;
            }

            $this->object = $class::create($values, $this->db);
            foreach ($values as $key => $value) {
                $this->object->$key = $value;
            }
        } else {
            if (! $this->object->hasConnection()) {
                $this->object->setConnection($this->db);
            }
        }

        return $this->object;
    }

    protected function extractChoicesFromPost($post)
    {
        $imports = [];

        foreach ($this->choiceElements as $other) {
            $name = $other->getName();
            if (array_key_exists($name, $post)) {
                $value = $post[$name];
                if (is_string($value)) {
                    $imports[] = $value;
                } elseif (is_array($value)) {
                    foreach ($value as $chosen) {
                        $imports[] = $chosen;
                    }
                }
            }
        }

        return $imports;
    }

    protected function assertResolvedImports()
    {
        if ($this->resolvedImports !== null) {
            return $this->resolvedImports;
        }

        $object = $this->object;

        if (! $object instanceof IcingaObject) {
            return $this->setResolvedImports(false);
        }
        if (! $object->supportsImports()) {
            return $this->setResolvedImports(false);
        }

        if ($this->hasBeenSent()) {
            // prefill special properties, required to resolve fields and similar
            $post = $this->getRequest()->getPost();

            $key = 'imports';
            if ($el = $this->getElement($key)) {
                if (array_key_exists($key, $post)) {
                    $imports = $post[$key];
                    if (! is_array($imports)) {
                        $imports = array($imports);
                    }
                    $imports = array_filter(array_values(array_merge(
                        $imports,
                        $this->extractChoicesFromPost($post)
                    )), 'strlen');

                    /** @var ZfElement $el */
                    $this->populate([$key => $imports]);
                    $el->setValue($imports);
                    if (! $this->tryToSetObjectPropertyFromElement($object, $el, $key)) {
                        return $this->resolvedImports = false;
                    }
                }
            } elseif ($this->presetImports) {
                $imports = array_values(array_merge(
                    $this->presetImports,
                    $this->extractChoicesFromPost($post)
                ));
                if (! $this->eventuallySetImports($imports)) {
                    return $this->resolvedImports = false;
                }
            } else {
                if (! empty($this->choiceElements)) {
                    if (! $this->eventuallySetImports($this->extractChoicesFromPost($post))) {
                        return $this->resolvedImports = false;
                    }
                }
            }

            foreach ($this->earlyProperties as $key) {
                if ($el = $this->getElement($key)) {
                    if (array_key_exists($key, $post)) {
                        $this->populate([$key => $post[$key]]);
                        $this->tryToSetObjectPropertyFromElement($object, $el, $key);
                    }
                }
            }
        }

        try {
            $object->listAncestorIds();
        } catch (NestingError $e) {
            $this->addUniqueErrorMessage($e->getMessage());
            return $this->resolvedImports = false;
        } catch (Exception $e) {
            $this->addException($e, 'imports');
            return $this->resolvedImports = false;
        }

        return $this->setResolvedImports();
    }

    protected function eventuallySetImports($imports)
    {
        try {
            $this->object()->set('imports', $imports);
            return true;
        } catch (Exception $e) {
            $this->addException($e, 'imports');
            return false;
        }
    }

    protected function tryToSetObjectPropertyFromElement(
        IcingaObject $object,
        ZfElement $element,
        $key
    ) {
        $old = null;
        try {
            $old = $object->get($key);
            $object->set($key, $element->getValue());
            $object->resolveUnresolvedRelatedProperties();

            if ($key === 'imports') {
                $object->imports()->getObjects();
            }
            return true;
        } catch (Exception $e) {
            if ($old !== null) {
                $object->set($key, $old);
            }
            $this->addException($e, $key);
            return false;
        }
    }

    public function setResolvedImports($resolved = true)
    {
        return $this->resolvedImports = $resolved;
    }

    public function isObject()
    {
        return $this->getSentOrObjectValue('object_type') === 'object';
    }

    public function isTemplate()
    {
        return $this->getSentOrObjectValue('object_type') === 'template';
    }

    // TODO: move to a subform
    protected function handleRanges(IcingaObject $object, & $values)
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

    public function addElementsToGroup($elements, $group, $order, $legend = null)
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

    protected function handleProperties(DbObject $object, & $values)
    {
        if ($this->hasBeenSent()) {
            foreach ($values as $key => $value) {
                try {
                    if ($key === 'imports' && ! empty($this->choiceElements)) {
                        if (! is_array($value)) {
                            $value = [$value];
                        }
                        foreach ($this->choiceElements as $element) {
                            $chosen = $element->getValue();
                            if (is_string($chosen)) {
                                $value[] = $chosen;
                            } elseif (is_array($chosen)) {
                                foreach ($chosen as $choice) {
                                    $value[] = $choice;
                                }
                            }
                        }
                    }
                    $object->set($key, $value);
                    if ($object instanceof IcingaObject) {
                        if ($this->resolvedImports !== false) {
                            $object->imports()->getObjects();
                        }
                    }
                } catch (Exception $e) {
                    $this->addException($e, $key);
                }
            }
        }
    }

    protected function loadInheritedProperties()
    {
        if ($this->assertResolvedImports()) {
            try {
                $this->showInheritedProperties($this->object());
            } catch (Exception $e) {
                $this->addException($e);
            }
        }
    }

    protected function showInheritedProperties(IcingaObject $object)
    {
        $inherited = $object->getInheritedProperties();
        $origins   = $object->getOriginsProperties();

        foreach ($inherited as $k => $v) {
            if ($v !== null && $k !== 'object_name') {
                $el = $this->getElement($k);
                if ($el) {
                    $this->setInheritedValue($el, $inherited->$k, $origins->$k);
                }
            }
        }
    }

    protected function removeEmptyProperties($props)
    {
        $result = array();
        foreach ($props as $k => $v) {
            if ($v !== null && $v !== '' && $v !== array()) {
                $result[$k] = $v;
            }
        }

        return $result;
    }

    protected function prepareFields($object)
    {
        if ($this->assertResolvedImports()) {
            $this->fieldLoader = new IcingaObjectFieldLoader($object);
            $this->fieldLoader->prepareElements($this);
        }

        return $this;
    }

    protected function setCustomVarValues($values)
    {
        if ($this->fieldLoader) {
            $this->fieldLoader->setValues($values, 'var_');
        }

        return $this;
    }

    protected function addFields()
    {
        if ($this->fieldLoader) {
            $this->fieldLoader->addFieldsToForm($this);
            $this->onAddedFields();
        }
    }

    protected function onAddedFields()
    {
    }

    // TODO: remove, used in sets I guess
    protected function fieldLoader($object)
    {
        if ($this->fieldLoader === null) {
            $this->fieldLoader = new IcingaObjectFieldLoader($object);
        }

        return $this->fieldLoader;
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

    /**
     * @param bool $importsFirst
     * @return $this
     */
    protected function groupMainProperties($importsFirst = false)
    {
        if ($importsFirst) {
            $elements = [
                'imports',
                'object_type',
                'object_name',
            ];
        } else {
            $elements = [
                'object_type',
                'object_name',
                'imports',
            ];
        }
        $elements = array_merge($elements, [
            'display_name',
            'host_id',
            'address',
            'address6',
            'groups',
            'inherited_groups',
            'applied_groups',
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
            'disable_checks', //Dependencies
            'disable_notifications',
            'ignore_soft_states',
            'apply_for',
            'create_live',
            'disabled',
        ]);

        // Add template choices to the main section
        /** @var \Zend_Form_Element $el */
        foreach ($this->getElements() as $key => $el) {
            if (substr($el->getName(), 0, 6) === 'choice') {
                $elements[] = $key;
            }
        }

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

    public function setElementValue($name, $value = null)
    {
        $el = $this->getElement($name);
        if (! $el) {
            // Not showing an error, as most object properties do not exist. Not
            // yet, because IMO this should be checked.
            // $this->addError(sprintf($this->translate('Form element "%s" does not exist'), $name));
            return;
        }

        if ($value !== null) {
            $el->setValue($value);
        }
    }

    public function setInheritedValue(ZfElement $el, $inherited, $inheritedFrom)
    {
        if ($inherited === null) {
            return;
        }

        $txtInherited = ' ' . $this->translate(' (inherited from "%s")');
        if ($el instanceof ZfSelect) {
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
            if (is_string($inherited) || is_int($inherited)) {
                $el->setAttrib('placeholder', $inherited . sprintf($txtInherited, $inheritedFrom));
            }
        }

        // We inherited a value, so no need to require the field
        $el->setRequired(false);
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
                $this->translate($this->getObjectShortClassName())
            );
                $object->store($this->db);
        } else {
            if ($this->isApiRequest()) {
                $this->setHttpResponseCode(304);
            }
            $msg = $this->translate('No action taken, object has not been modified');
        }

        $this->setObjectSuccessUrl();
        $this->beforeSuccessfulRedirect();
        $this->redirectOnSuccess($msg);
    }

    protected function setObjectSuccessUrl()
    {
        $object = $this->object();

        if ($object instanceof IcingaObject) {
            $this->setSuccessUrl(
                'director/' . strtolower($this->getObjectShortClassName()),
                $object->getUrlParams()
            );
        } elseif ($object->hasProperty('id')) {
            $this->setSuccessUrl($this->getSuccessUrl()->with('id', $object->getProperty('id')));
        }
    }

    protected function beforeSuccessfulRedirect()
    {
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

    public function setObject(DbObject $object)
    {
        $this->object = $object;
        if ($this->db === null) {
            /** @var Db $connection */
            $connection = $object->getConnection();
            $this->setDb($connection);
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

    protected function getObjectShortClassName()
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
        if ($this->object !== null) {
            $this->setDefaultsFromObject($this->object);
        }
        $this->prepareFields($this->object());
        if ($this->hasBeenSent()) {
            $this->handlePost();
        }
        try {
            $this->loadInheritedProperties();
            $this->addFields();
        } catch (Exception $e) {
            $this->addUniqueException($e);
        }
    }

    protected function handlePost()
    {
        $object = $this->object();
        if ($this->shouldBeDeleted()) {
            $this->deleteObject($object);
        }

        $post = $this->getRequest()->getPost();
        $this->populate($post);
        $values = $this->getValues();

        if ($object instanceof IcingaObject) {
            $this->setCustomVarValues($post);
        }

        $this->handleProperties($object, $values);

        // TODO: get rid of this
        if ($object instanceof IcingaObject) {
            $this->handleRanges($object, $values);
        }
    }

    protected function setDefaultsFromObject(DbObject $object)
    {
        /** @var ZfElement $element */
        foreach ($this->getElements() as $element) {
            $key = $element->getName();
            if ($object->hasProperty($key)) {
                $value = $object->get($key);
                if ($object instanceof IcingaObject) {
                    if ($object->propertyIsRelatedSet($key)) {
                        if (! count((array) $value)) {
                            continue;
                        }
                    }
                }

                if ($value !== null && $value !== []) {
                    $element->setValue($value);
                }
            }
        }
    }

    protected function deleteObject($object)
    {
        if ($object instanceof IcingaObject && $object->hasProperty('object_name')) {
            $msg = sprintf(
                '%s "%s" has been removed',
                $this->translate($this->getObjectShortClassName()),
                $object->getObjectName()
            );
        } else {
            $msg = sprintf(
                '%s has been removed',
                $this->translate($this->getObjectShortClassName())
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

    protected function abortDeletion()
    {
        if ($this->hasDeleteButton()) {
            $this->setSentValue($this->deleteButtonName, 'ABORTED');
        }
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
                if ($name === 'object_type' && $this->preferredObjectType) {
                    return $this->preferredObjectType;
                }
                if ($name === 'imports' && $this->presetImports) {
                    return $this->presetImports;
                }
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
                if ($this->assertResolvedImports()) {
                    $objectProperty = $object->getResolvedProperty($name);
                } else {
                    $objectProperty = $object->$name;
                }
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
        /** @var DbObject $class */
        $class = $this->getObjectClassname();
        $this->object = $class::load($id, $this->db);

        // TODO: hmmmm...
        if (! is_array($id) && $this->object->getKeyName() === 'id') {
            $this->addHidden('id', $id);
        }

        return $this;
    }

    protected function addRange($key, $range)
    {
        $this->addElement('text', 'range_' . $key, array(
            'label' => 'ranges.' . $key,
            'value' => $range->range_value
        ));
    }

    /**
     * @param Db $db
     * @return $this
     */
    public function setDb(Db $db)
    {
        if ($this->object !== null) {
            $this->object->setConnection($db);
        }

        parent::setDb($db);
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
            return $this;
        }

        if ($this->preferredObjectType) {
            $this->addHidden('object_type', $this->preferredObjectType);
            return $this;
        }

        $object = $this->object();

        if ($object->supportsImports()) {
            $templates = $this->enumAllowedTemplates();

            if (empty($templates) && $this->getObjectShortClassName() !== 'Command') {
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

    protected function addZoneElement($all = false)
    {
        if ($all || $this->isTemplate()) {
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

    /**
     * @param $type
     * @return $this
     */
    protected function addChoices($type)
    {
        $connection = $this->getDb();
        $choiceType = 'TemplateChoice' . ucfirst($type);
        $choices = IcingaObject::loadAllByType($choiceType, $connection);
        foreach ($choices as $choice) {
            $this->addChoiceElement($choice);
        }

        return $this;
    }

    protected function addChoiceElement(IcingaTemplateChoice $choice)
    {
        $imports = $this->object()->listImportNames();
        $element = $choice->createFormElement($this, $imports);
        $this->addElement($element);
        $this->choiceElements[$element->getName()] = $element;
        return $this;
    }

    /**
     * @param bool $required
     * @return $this
     */
    protected function addImportsElement($required = null)
    {
        if ($this->presetImports) {
            return $this;
        }

        if (in_array($this->getObjectShortClassName(), ['TimePeriod'])) {
            $required = false;
        } else {
            $required = $required !== null ? $required : !$this->isTemplate();
        }
        $enum = $this->enumAllowedTemplates();
        if (empty($enum)) {
            if ($required) {
                if ($this->hasBeenSent()) {
                    $this->addError($this->translate('No template has been chosen'));
                } else {
                    if ($this->hasPermission('director/admin')) {
                        $html = $this->translate('Please define a related template first');
                    } else {
                        $html = $this->translate('No related template has been provided yet');
                    }
                    $this->addHtml('<p class="warning">' . $html . '</p>');
                }
            }
            return $this;
        }

        $db = $this->getDb()->getDbAdapter();
        $object = $this->object;
        if ($object->supportsChoices()) {
            $choiceNames = $db->fetchCol(
                $db->select()->from(
                    $this->object()->getTableName(),
                    'object_name'
                )->where('template_choice_id IS NOT NULL')
            );
        } else {
            $choiceNames = [];
        }

        $type = $object->getShortTableName();
        $this->addElement('extensibleSet', 'imports', array(
            'label'        => $this->translate('Imports'),
            'description'  => $this->translate(
                'Importable templates, add as many as you want. Please note that order'
                . ' matters when importing properties from multiple templates: last one'
                . ' wins'
            ),
            'required'     => $required,
            'spellcheck'   => 'false',
            'hideOptions'  => $choiceNames,
            'suggest'      => "${type}templates",
            // 'multiOptions' => $this->optionallyAddFromEnum($enum),
            'sorted'       => true,
            'value'        => $this->presetImports,
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

    /**
     * @param bool $force
     *
     * @return $this
     */
    protected function addCheckCommandElements($force = false)
    {
        if (! $force && ! $this->isTemplate()) {
            return $this;
        }

        $this->addElement('text', 'check_command', array(
            'label' => $this->translate('Check command'),
            'description'  => $this->translate('Check command definition'),
            // 'multiOptions' => $this->optionalEnum($this->db->enumCheckcommands()),
            'class'        => 'autosubmit director-suggest', // This influences fields
            'data-suggestion-context' => 'checkcommandnames',
            'value' => $this->getObject()->get('check_command')
        ));
        $this->getDisplayGroup('object_definition')
            // ->addElement($this->getElement('check_command_id'))
            ->addElement($this->getElement('check_command'));

        $eventCommands = $this->db->enumEventcommands();

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

    protected function addCheckExecutionElements($force = false)
    {
        if (! $force && ! $this->isTemplate()) {
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

        $this->addElement(
            'text',
            'check_timeout',
            array(
                'label' => $this->translate('Check timeout'),
                'description' => $this->translate(
                    "Check command timeout in seconds. Overrides the CheckCommand's timeout attribute"
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
            'check_timeout',
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
            return [];
        }

        $id = $object->get('id');

        if (array_key_exists($id, $tpl)) {
            unset($tpl[$id]);
        }

        return array_combine($tpl, $tpl);
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

    /**
     * Add an assign_filter form element
     *
     * Forms should use this helper method for objects using the typical
     * assign_filter column
     *
     * @param array  $properties Form element properties
     *
     * @return $this
     */
    protected function addAssignFilter($properties)
    {
        if (!$this->object || !$this->object->supportsAssignments()) {
            return $this;
        }

        $this->addFilterElement('assign_filter', $properties);
        $el = $this->getElement('assign_filter');

        $this->addDisplayGroup(array($el), 'assign', array(
            'decorators' => array(
                'FormElements',
                array('HtmlTag', array('tag' => 'dl')),
                'Fieldset',
            ),
            'order'  => 30,
            'legend' => $this->translate('Assign where')
        ));

        return $this;
    }

    /**
     * Add a dataFilter element with fitting decorators
     *
     * TODO: Evaluate whether parts or all of this could be moved to the element
     * class.
     *
     * @param string $name       Element name
     * @param array  $properties Form element properties
     *
     * @return $this
     */
    protected function addFilterElement($name, $properties)
    {
        $this->addElement('dataFilter', $name, $properties);
        $el = $this->getElement($name);

        $ddClass = 'full-width';
        if (array_key_exists('required', $properties) && $properties['required']) {
            $ddClass .= ' required';
        }

        $el->clearDecorators()
            ->addDecorator('ViewHelper')
            ->addDecorator('Errors')
            ->addDecorator('Description', array('tag' => 'p', 'class' => 'description'))
            ->addDecorator('HtmlTag', array(
                'tag'   => 'dd',
                'class' => $ddClass,
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

    /**
     * @param  string $permission
     * @return bool
     */
    public function hasPermission($permission)
    {
        return Util::hasPermission($permission);
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
}
