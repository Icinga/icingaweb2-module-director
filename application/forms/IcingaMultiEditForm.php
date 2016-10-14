<?php

namespace Icinga\Module\Director\Forms;

use Icinga\Module\Director\Web\Form\IcingaObjectFieldLoader;
use Icinga\Module\Director\Web\Form\DirectorObjectForm;
use Zend_Form_Element as ZfElement;

class IcingaMultiEditForm extends DirectorObjectForm
{
    private $objects;

    private $elementGroupMap;

    public function setObjects($objects)
    {
        $this->objects = $objects;
        $this->object = current($this->objects);
        $this->db = $this->object()->getConnection();
        return $this;
    }

    public function setup()
    {
        $object = $this->object;
        $this->addImportsElement();
        // $this->addDisabledElement();

        $loader = new IcingaObjectFieldLoader($object);
        $loader->addFieldsToForm($this);

        $this->makeVariants($this->getElement('imports'));
        // $this->makeVariants($this->getElement('disabled'));
        foreach ($this->getElements() as $el) {
            $name =$el->getName();
            if (substr($name, 0, 4) === 'var_') {
                $this->makeVariants($el);
            }
        }

        $this->setButtons();
    }

    /**
     * No default objects behaviour
     */
    protected function onRequest()
    {
    }

    public function onSuccess()
    {
        foreach ($this->getValues() as $key => $value) {
            $parts = preg_split('/_/', $key);
            $objectsSum = array_pop($parts);
            $valueSum = array_pop($parts);
            $property = implode('_', $parts);

            $found = false;
            foreach ($this->getVariants($property) as $json => $objects) {
                if ($valueSum !== sha1($json)) {
                    continue;
                }

                if ($objectsSum !== sha1(json_encode($objects))) {
                    continue;
                }

                $found = true;
                if (substr($property, 0, 4) === 'var_') {
                    $property = 'vars.' . substr($property, 4);
                }

                foreach ($this->getObjects($objects) as $object) {
                    $object->$property = $value;
                }
            }
        }

        $modified = 0;
        foreach ($this->objects as $object) {
            if ($object->hasBeenModified()) {
                $modified++;
                $object->store();
            }
        }

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

    protected function getDisplayGroupForElement(ZfElement $element)
    {
        if ($this->elementGroupMap === null) {
            $this->resolveDisplayGroups();
        }

        $name = $element->getName();
        if (array_key_exists($name, $this->elementGroupMap)) {
            return $this->getDisplayGroup($this->elementGroupMap[$name]);
        } else {
            return null;
        }
    }

    protected function resolveDisplayGroups()
    {
        $this->elementGroupMap = array();

        foreach ($this->getDisplayGroups() as $group) {
            $groupName = $group->getName();
            foreach ($group->getElements() as $name => $e) {
                $this->elementGroupMap[$name] = $groupName;
            }
        }
    }

    protected function makeVariants(ZfElement $element)
    {
        if (! $element) {
            return $this;
        }

        $key = $element->getName();
        $this->removeElement($key);
        $label = $element->getLabel();
        $group = $this->getDisplayGroupForElement($element);
        $description = $element->getDescription();

        foreach ($this->getVariants($key) as $json => $objects) {
            $value = json_decode($json);
            $checksum = sha1($json) . '_' . sha1(json_encode($objects));

            $v = clone($element);
            $v->setName($key . '_' . $checksum);
            $v->setDescription($description . '. ' . $this->descriptionForObjects($objects));
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

    protected function enumTemplates()
    {
        $object = $this->object();
        $tpl = $this->db()->enumIcingaTemplates($object->getShortTableName());
        if (empty($tpl)) {
            return array();
        }

        if (empty($tpl)) {
            return array();
        }

        $tpl = array_combine($tpl, $tpl);
        return $tpl;
    }

    protected function db()
    {
        if ($this->db === null) {
            $this->db = $this->object()->getConnection();
        }

        return $this->db;
    }

    protected function getObjects($names)
    {
        $res = array();
        foreach ($names as $name) {
            $res[$name] = $this->objects[$name];
        }

        return $res;
    }
}
