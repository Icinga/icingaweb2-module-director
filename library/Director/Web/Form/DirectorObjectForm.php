<?php

namespace Icinga\Module\Director\Web\Form;

abstract class DirectorObjectForm extends QuickForm
{
    protected $db;

    protected $object;

    private $objectName;

    private $className;

    public function onSuccess()
    {
        if ($this->object) {
            $this->object->setProperties($this->getValues())->store();
            $this->redirectOnSuccess(
                sprintf(
                    $this->translate('The Icinga %s has successfully been stored'),
                    $this->translate($this->getObjectName())
                )
            );
        } else {
            $class = $this->getObjectClassname();
            $class::create($this->getValues())->store($this->db);
            $this->redirectOnSuccess(
                sprintf(
                    $this->translate('A new Icinga %s has successfully been created'),
                    $this->translate($this->getObjectName())
                )
            );
        }
    }

    protected function optionalEnum($enum)
    {
        return array(
            null => $this->translate('- please choose -')
        ) + $enum;
    }

    protected function optionalBoolean($key, $label, $description)
    {
        return $this->addElement('select', $key, array(
            'label' => $label,
            'description' => $description,
            'multiOptions' => $this->selectBoolean()
        ));
    }

    protected function selectBoolean()
    {
        return array(
            null => $this->translate('- not set -'),
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

    public function loadObject($id)
    {
        $class = $this->getObjectClassname();
        $this->object = $class::load($id, $this->db);
        $this->addHidden('id');
        $this->setDefaults($this->object->getProperties());
        return $this;
    }

    public function setDb($db)
    {
        $this->db = $db;
        if ($this->hasElement('parent_zone_id')) {
            $this->getElement('parent_zone_id')
                ->setMultiOptions($this->optionalEnum($db->enumZones()));
        }
        if ($this->hasElement('zone_id')) {
            $this->getElement('zone_id')
                ->setMultiOptions($this->optionalEnum($db->enumZones()));
        }
        if ($this->hasElement('check_command_id')) {
            $this->getElement('check_command_id')
                ->setMultiOptions($this->optionalEnum($db->enumCheckCommands()));
        }
        if ($this->hasElement('command_id')) {
            $this->getElement('command_id')
                ->setMultiOptions($this->optionalEnum($db->enumCommands()));
        }
        return $this;
    }

    private function dummyForTranslation()
    {
        $this->translate('Host');
        $this->translate('Service');
        $this->translate('Zone');
        $this->translate('Command');
        // ... TBC
    }
}
