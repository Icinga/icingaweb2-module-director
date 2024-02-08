<?php

namespace Icinga\Module\Director\Forms;

use Icinga\Module\Director\Data\Db\DbObject;
use Icinga\Module\Director\Hook\IcingaObjectFormHook;
use Icinga\Module\Director\Web\Form\IcingaObjectFieldLoader;
use Icinga\Module\Director\Web\Form\DirectorObjectForm;
use Icinga\Module\Director\Web\Form\QuickForm;
use Zend_Form_Element as ZfElement;

class IcingaMultiEditForm extends DirectorObjectForm
{
    /** @var  DbObject[] */
    private $objects;

    private $elementGroupMap;

    /** @var  QuickForm */
    private $relatedForm;

    private $propertiesToPick;

    public function setObjects($objects)
    {
        $this->objects = $objects;
        $this->object = current($this->objects);
        $this->db = $this->object()->getConnection();
        return $this;
    }

    public function isMultiObjectForm()
    {
        return true;
    }

    public function pickElementsFrom(QuickForm $form, $properties)
    {
        $this->relatedForm = $form;
        $this->propertiesToPick = $properties;
        return $this;
    }

    public function setup()
    {
        $object = $this->object;

        $loader = new IcingaObjectFieldLoader($object);
        $loader->prepareElements($this);
        $loader->addFieldsToForm($this);

        if ($form = $this->relatedForm) {
            if ($form instanceof DirectorObjectForm) {
                $form->setDb($object->getConnection())
                    ->setObject($object);
            }

            $form->prepareElements();
        } else {
            $this->propertiesToPick = array();
        }

        foreach ($this->propertiesToPick as $property) {
            if ($el = $form->getElement($property)) {
                $this->makeVariants($el);
            }
        }

        /** @var \Zend_Form_Element $el */
        foreach ($this->getElements() as $el) {
            $name = $el->getName();
            if (substr($name, 0, 4) === 'var_') {
                $this->makeVariants($el);
            }
        }

        $this->setButtons();
    }

    public function onSuccess()
    {
        foreach ($this->getValues() as $key => $value) {
            $this->setSubmittedMultiValue($key, $value);
        }

        $modified = $this->storeModifiedObjects();
        if ($modified === 0) {
            $msg = $this->translate('No object has been modified');
        } elseif ($modified === 1) {
            $msg = $this->translate('One object has been modified');
        } else {
            $msg = sprintf(
                $this->translate('%d objects have been modified'),
                $modified
            );
        }

        $this->redirectOnSuccess($msg);
    }

    /**
     * No default objects behaviour
     */
    protected function onRequest()
    {
        IcingaObjectFormHook::callOnSetup($this);
        if ($this->hasBeenSent()) {
            $this->handlePost();
        }
    }

    protected function handlePost()
    {
        $this->callOnRequestCallables();
        if ($this->shouldBeDeleted()) {
            $this->deleteObjects();
        }
    }

    protected function setSubmittedMultiValue($key, $value)
    {
        $parts = preg_split('/_/', $key);
        $objectsSum = array_pop($parts);
        $valueSum = array_pop($parts);
        $property = implode('_', $parts);

        if ($value === '') {
            $value = null;
        }

        foreach ($this->getVariants($property) as $json => $objects) {
            if ($valueSum !== sha1($json)) {
                continue;
            }

            if ($objectsSum !== sha1(json_encode($objects))) {
                continue;
            }

            if (substr($property, 0, 4) === 'var_') {
                $property = 'vars.' . substr($property, 4);
            }

            foreach ($this->getObjects($objects) as $object) {
                $object->$property = $value;
            }
        }
    }

    protected function storeModifiedObjects()
    {
        $modified = 0;
        $store = $this->getDbObjectStore();
        foreach ($this->objects as $object) {
            if ($object->hasBeenModified()) {
                $modified++;
                $store->store($object);
            }
        }

        return $modified;
    }

    protected function getDisplayGroupForElement(ZfElement $element)
    {
        if ($this->elementGroupMap === null) {
            $this->resolveDisplayGroups();
        }

        $name = $element->getName();
        if (array_key_exists($name, $this->elementGroupMap)) {
            $groupName = $this->elementGroupMap[$name];

            if ($group = $this->getDisplayGroup($groupName)) {
                return $group;
            } elseif ($this->relatedForm) {
                return $this->stealDisplayGroup($groupName, $this->relatedForm);
            }
        }

        return null;
    }

    protected function stealDisplayGroup($name, QuickForm $form)
    {
        if ($group = $form->getDisplayGroup($name)) {
            $group = clone($group);
            $group->setElements(array());
            $this->_displayGroups[$name] = $group;
            $this->_order[$name] = $group->getOrder();
            $this->_orderUpdated = true;

            return $group;
        }

        return null;
    }

    protected function resolveDisplayGroups()
    {
        $this->elementGroupMap = array();
        if ($form = $this->relatedForm) {
            $this->extractFormDisplayGroups($form);
        }

        $this->extractFormDisplayGroups($this);
    }

    protected function extractFormDisplayGroups(QuickForm $form)
    {
        /** @var \Zend_Form_DisplayGroup $group */
        foreach ($form->getDisplayGroups() as $group) {
            $groupName = $group->getName();
            foreach ($group->getElements() as $name => $e) {
                $this->elementGroupMap[$name] = $groupName;
            }
        }
    }

    protected function makeVariants(ZfElement $element)
    {
        $key = $element->getName();
        $this->removeElement($key);
        $label = $element->getLabel();
        $group = $this->getDisplayGroupForElement($element);
        $description = $element->getDescription();

        foreach ($this->getVariants($key) as $json => $objects) {
            $value = json_decode($json);
            $checksum = sha1($json) . '_' . sha1(json_encode($objects));

            $v = clone($element);
            if ($key == "imports") {
                $v->setAttrib('hideOptions', []);
            }
            $v->setName($key . '_' . $checksum);
            $v->setDescription($description . ' ' . $this->descriptionForObjects($objects));
            $v->setLabel($label . $this->labelCount($objects));
            $v->setValue($value);
            if ($group) {
                $group->addElement($v);
            }
            $this->addElement($v);
        }
    }

    protected function getVariants($key)
    {
        $variants = array();
        if (substr($key, 0, 4) === 'var_') {
            $key = 'vars.' . substr($key, 4);
        }

        foreach ($this->objects as $name => $object) {
            $value = json_encode($object->$key);
            if (! array_key_exists($value, $variants)) {
                $variants[$value] = array();
            }

            $variants[$value][] = $name;
        }

        foreach ($variants as & $objects) {
            natsort($objects);
        }

        return $variants;
    }

    protected function descriptionForObjects($list)
    {
        return sprintf(
            $this->translate('Changing this value affects %d object(s): %s'),
            count($list),
            implode(', ', $list)
        );
    }

    protected function labelCount($list)
    {
        return ' (' . count($list) . ')';
    }

    protected function db()
    {
        if ($this->db === null) {
            $this->db = $this->object()->getConnection();
        }

        return $this->db;
    }

    public function getObjects($names = null)
    {
        if ($names === null) {
            return $this->objects;
        }

        $res = array();

        foreach ($names as $name) {
            $res[$name] = $this->objects[$name];
        }

        return $res;
    }

    protected function deleteObjects()
    {
        $msg = sprintf(
            '%d objects of type "%s" have been removed',
            count($this->objects),
            $this->translate($this->object->getShortTableName())
        );

        $store = $this->getDbObjectStore();
        foreach ($this->objects as $object) {
            $store->delete($object);
        }

        if ($this->listUrl) {
            $this->setSuccessUrl($this->listUrl);
        }

        $this->redirectOnSuccess($msg);
    }
}
