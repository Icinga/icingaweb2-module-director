<?php

namespace Icinga\Module\Director\Web\Form;

use Icinga\Module\Director\Objects\IcingaObject;

abstract class DirectorObjectForm extends QuickForm
{
    protected $db;

    protected $object;

    private $objectName;

    private $className;

    protected function object($values = array())
    {
        if ($this->object === null) {
            $class = $this->getObjectClassname();
            $this->object = $class::create($values, $this->db);
        } else {
            if (! $this->object->hasConnection()) {
                $this->object->setConnection($this->db);
            }
            $this->object->setProperties($values);
        }

        return $this->object;
    }

    protected function onSetup()
    {
        $object = $this->object();

        if (! $object instanceof IcingaObject) {
            return;
        }

        if ($object->supportsCustomVars()) {
            $this->addElement('note', '_newvar_hint', array('label' => 'New custom variable'));
            $this->addElement('text', '_newvar_name', array(
                'label' => 'Name'
            ));
            $this->addElement('text', '_newvar_value', array(
                'label' => 'Value'
            ));
            $this->addElement('select', '_newvar_format', array(
                'label'        => 'Type',
                'multiOptions' => array('string' => $this->translate('String'))
            ));
        }

        if (false && $object->supportsRanges()) {
            /* TODO implement when new logic is there
            $this->addElement('note', '_newrange_hint', array('label' => 'New range'));
            $this->addElement('text', '_newrange_name', array(
                'label' => 'Name'
            ));
            $this->addElement('text', '_newrange_value', array(
                'label' => 'Value'
            ));
            */
        }
    }

    protected function handleIcingaObject(& $values)
    {
        $object = $this->object();
        $handled = array();

        if ($object->supportsGroups()) {
            if (array_key_exists('groups', $values)) {
                $object->groups()->set(
                   preg_split('/\s*,\s*/', $values['groups'], -1, PREG_SPLIT_NO_EMPTY)
                );
                $handled['groups'] = true;
            }
        }

        if ($this->object->supportsCustomVars()) {
            $vars = array();
            $newvar = array(
                'type'  => 'string',
                'name'  => null,
                'value' => null,
            );

            foreach ($values as $key => $value) {
                if (substr($key, 0, 4) === 'var_') {
                    $vars[substr($key, 4)] = $value;
                    $handled[$key] = true;
                }

                if (substr($key, 0, 8) === '_newvar_') {
                    $newvar[substr($key, 8)] = $value;
                    $handled[$key] = true;
                }
            }

            foreach ($vars as $k => $v) {
                $this->object->vars()->$k = $v;
            }

            if ($newvar['name'] && $newvar['value']) {
                $this->object->vars()->{$newvar['name']} = $newvar['value'];
            }
        }

        if ($object->supportsImports()) {
            if (array_key_exists('imports', $values)) {
                $object->imports()->set(
                    preg_split('/\s*,\s*/', $values['imports'], -1, PREG_SPLIT_NO_EMPTY)
                );
                $handled['imports'] = true;
            }
        }

        if ($object->supportsRanges()) {
            $object->ranges()->set(array(
                'monday' => 'eins',
                'tuesday' => '00:00-24:00',
                'sunday'    => 'zwei',
            ));
        }

        foreach ($handled as $key => $value) {
            unset($values[$key]);
        }
    }

    public function onSuccess()
    {
        $object = $this->object;
        $values = $this->getValues();
        if ($object instanceof IcingaObject) {
            $this->handleIcingaObject($values);
        }
        $object->setProperties($values);
        $msg = sprintf(
            $object->hasBeenLoadedFromDb()
            ? 'The Icinga %s has successfully been stored'
            : 'A new Icinga %s has successfully been created',
            $this->translate($this->getObjectName())
        );

        $object->store($this->db);
        $this->redirectOnSuccess($msg);
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
        $this->prepareElements();
        $class = $this->getObjectClassname();
        $this->object = $class::load($id, $this->db);
        if (! is_array($id)) {
            $this->addHidden('id');
        }
        $this->setDefaults($this->object->getProperties());
        if (! $this->object instanceof IcingaObject) {
            return $this;
        }

        if ($submit = $this->getElement('submit')) {
            $this->removeElement('submit');
        }

        if ($this->object->supportsGroups()) {
            $this->getElement('groups')->setValue(
                implode(', ', $this->object->groups()->listGroupNames())
            );
        }

        if ($this->object->supportsImports()) {
            $this->getElement('imports')->setValue(
                implode(', ', $this->object->imports()->listImportNames())
            );
        }

        if ($this->object->supportsCustomVars()) {
            foreach ($this->object->vars() as $key => $value) {
                $this->addCustomVar($key, $value);
            }
        }

        if ($this->object->supportsRanges()) {
            /* TODO implement when new logic for customvars is there
            foreach ($this->object->ranges()->getRanges() as $key => $value) {
                $this->addRange($key, $value);
            }
            */
        }

        if ($submit) {
            $this->addElement($submit);
        }

        if (! $this->hasBeenSubmitted()) {
            $this->beforeValidation($this->object->getProperties());
        }
        return $this;
    }

    protected function addCustomVar($key, $range)
    {
        $this->addElement('text', 'var_' . $key, array(
            'label' => 'vars.' . $key,
            'value' => $range->getValue()
        ));
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
        if ($this->hasElement('parent_zone_id')) {
            $this->getElement('parent_zone_id')
                ->setMultiOptions($this->optionalEnum($db->enumZones()));
        }
        if ($this->hasElement('host_id')) {
            $this->getElement('host_id')
                ->setMultiOptions($this->optionalEnum($db->enumHosts()));
        }
        if ($this->hasElement('hostgroup_id')) {
            $this->getElement('hostgroup_id')
                ->setMultiOptions($this->optionalEnum($db->enumHostgroups()));
        }
        if ($this->hasElement('service_id')) {
            $this->getElement('service_id')
                ->setMultiOptions($this->optionalEnum($db->enumServices()));
        }
        if ($this->hasElement('servicegroup_id')) {
            $this->getElement('servicegroup_id')
                ->setMultiOptions($this->optionalEnum($db->enumServicegroups()));
        }
        if ($this->hasElement('user_id')) {
            $this->getElement('user_id')
                ->setMultiOptions($this->optionalEnum($db->enumUsers()));
        }
        if ($this->hasElement('usergroup_id')) {
            $this->getElement('usergroup_id')
                ->setMultiOptions($this->optionalEnum($db->enumUsergroups()));
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
