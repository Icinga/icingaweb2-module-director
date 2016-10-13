<?php

namespace Icinga\Module\Director\Forms;

use Icinga\Module\Director\Web\Form\QuickForm;

class IcingaMultiEditForm extends QuickForm
{
    private $objects;

    private $object;

    private $db;

    public function setObjects($objects)
    {
        $this->objects = $objects;
        return $this;
    }

    public function setup()
    {
        $this->addImportsElements();//->setButtons();
    }

    public function onSuccess()
    {
/*
echo '<pre>';
print_r($this->getVariants('imports'));
print_r($this->getValues());
echo '</pre>';
*/
        foreach ($this->getValues() as $key => $value) {
            $parts = preg_split('/_/', $key);
            $objectsSum = array_pop($parts);
            $valueSum = array_pop($parts);
            $property = implode('_', $parts);
//printf("Got %s: %s -> %s<br>", $property, $valueSum, $objectsSum);

            $found = false;
            foreach ($this->getVariants($property) as $json => $objects) {
                if ($valueSum !== sha1($json)) {
                    continue;
                }

                if ($objectsSum !== sha1(json_encode($objects))) {
                    continue;
                }

                $found = true;

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
            $this->setSuccessMessage($this->translate('No object has been modified'));
        } elseif ($modified === 1) {
            $this->setSuccessMessage($this->translate('One object has been modified'));
        } else {
            $this->setSuccessMessage(
                sprintf(
                    $this->translate('%d objects have been modified'),
                    $modified
                )
            );
        }

        parent::onSuccess();
    }

    protected function getVariants($key)
    {
        $variants = array();
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

    protected function addImportsElements()
    {
        $enum = $this->enumTemplates();
        if (empty($enum)) {
            return $this;
        }

        foreach ($this->getVariants('imports') as $json => $objects) {
            $value = json_decode($json);
            $checksum = sha1($json) . '_' . sha1(json_encode($objects));
            $this->addElement('extensibleSet', 'imports_' . $checksum, array(
                'label'        => $this->translate('Imports') . $this->labelCount($objects),
                'description'  => $this->translate(
                    'Importable templates, add as many as you want. Please note that order'
                    . ' matters when importing properties from multiple templates: last one'
                    . ' wins'
                ) . '. ' . $this->descriptionForObjects($objects),
                'required'     => !$this->object()->isTemplate(),
                'multiOptions' => $this->optionallyAddFromEnum($enum),
                'value'        => $value,
                'sorted'       => true,
                'class'        => 'autosubmit'
            ));
        }

        return $this;
    }

    public function optionallyAddFromEnum($enum)
    {
        return array(
            null => $this->translate('- click to add more -')
        ) + $enum;
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

    protected function object()
    {
        if ($this->object === null) {
            $this->object = current($this->objects);
        }

        return $this->object;
    }
}
