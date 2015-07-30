<?php

namespace Icinga\Module\Director\Web\Form;

use Icinga\Module\Director\Objects\IcingaObject;
use Icinga\Module\Director\Objects\DirectorDatafield;
use Zend_Form_Element_Select as Zf_Select;

abstract class DirectorObjectForm extends QuickForm
{
    protected $db;

    protected $object;

    private $objectName;

    private $className;

    private $objectType = 'object';

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

    protected function isTemplate()
    {
        return $this->objectType === 'template';
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

        if ($object->supportsCustomVars()) {

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
                $value = $values['imports'];

                // TODO: Compat for comma separated string, check if still needed
                if (! is_array($value)) {
                    $value = preg_split('/\s*,\s*/', $value, -1, PREG_SPLIT_NO_EMPTY);
                }

                $object->imports()->set($value);
                $handled['imports'] = true;
                $object->clearImportedObjects();
            }
        }

        if ($object->supportsRanges()) {
            $object->ranges()->set(array(
                'monday'  => 'eins',
                'tuesday' => '00:00-24:00',
                'sunday'  => 'zwei',
            ));
        }

        foreach ($handled as $key => $value) {
            unset($values[$key]);
        }
    }

    public function setObjectType($type)
    {
        $this->objectType = $type;
        return $this;
    }

    public function addFields()
    {
        $object = $this->object();
        $fields = $object->getResolvedFields();
        $vars   = $object->vars();

        foreach ($fields as $field) {
            $varname = $field->varname;
            if (isset($vars->$varname)) {
                $value = $vars->{$varname}->getValue();
            } else {
                $value = null;
            }
$inherited = null; // Just testing
            $this->addField($field, $value, $inherited);
        }
    }

    protected function addField($field, $value = null, $inherited = null)
    {
        $datafield = DirectorDatafield::load($field->datafield_id, $this->getDb());
        $datatype = new $datafield->datatype;
        $datatype->setSettings($datafield->getSettings());

        $name = 'var_' . $datafield->varname;
        $el = $datatype->getFormElement($name, $this);

        $el->setLabel($datafield->caption);
        $el->setDescription($datafield->description);

        if ($field->is_required === 'y') {
            $el->setRequired(true);
        }

        $this->addElement($el);
        $this->setElementValue($name, $value, $inherited);
    }

    protected function setElementValue($name, $value = null, $inherited = null)
    {
        $el = $this->getElement($name);
        if (! $el) {
            return;
        }

        if ($value !== null) {
            $el->setValue($value);
        }

        if ($inherited === null) {
            return;
        }

        $strInherited = $this->translate('(inherited)');
        if ($el instanceof Zf_Select) {
            $multi = $el->getMultiOptions();
            if (array_key_exists($inherited, $multi)) {
                $multi[null] = $multi[$inherited] . ' ' . $strInherited;
            } else {
                $multi[null] = $strInherited;
            }
            $el->setMultiOptions($multi);
        } else {
            $el->setAttrib('placeholder', $inherited . ' ' . $strInherited);
        }
    }

    public function onSuccess()
    {
        $object = $this->object;
        $values = $this->getValues();
        if ($object instanceof IcingaObject) {
            $this->handleIcingaObject($values);
            if (! array_key_exists('object_type', $values)) {
                $object->object_type = $this->objectType;
            }
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
        $object = $this->object = $class::load($id, $this->db);
        if ($object instanceof IcingaObject) {
            $this->objectType = $object->object_type;
        }

        if (! is_array($id)) {
            $this->addHidden('id');
        }
        $this->prepareElements();

        $props = $object->getProperties();
        if (! $object instanceof IcingaObject) {
            $this->setDefaults($props);
            return $this;
        }

        if ($submit = $this->getElement('submit')) {
            $this->removeElement('submit');
        }

        if ($object->supportsGroups()) {
            $this->getElement('groups')->setValue(
                implode(', ', $object->groups()->listGroupNames())
            );
        }

        if ($object->supportsImports()) {
            $el = $this->getElement('imports');
            if ($el) {
                $el->setMultiOptions($this->enumAllowedTemplates());
                $el->setValue($object->imports()->listImportNames());
            }
        }

        if ($object->supportsFields() && ! $object->isTemplate()) {
            foreach ($this->object->vars() as $key => $value) {
                $this->addCustomVar($key, $value);
            }
        }

        if ($object->supportsRanges()) {
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

        return $this;
    }

    protected function addZoneElement()
    {
        $this->addElement('select', 'zone_id', array(
            'label' => $this->translate('Cluster Zone'),
            'description'  => $this->translate('Icinga cluster zone'),
            'multiOptions' => $this->optionalEnum($this->db->enumZones())
        ));

        return $this;
    }

    protected function addCheckCommandElement()
    {
        $this->addElement('select', 'check_command_id', array(
            'label' => $this->translate('Check command'),
            'description'  => $this->translate('Check command definition'),
            'multiOptions' => $this->optionalEnum($this->db->enumCheckCommands())
        ));

        return $this;
    }

    protected function addImportsElement()
    {
        $this->addElement('multiselect', 'imports', array(
            'label'        => $this->translate('Imports'),
            'description'  => $this->translate('Importable templates'),
            'multiOptions' => $this->enumAllowedTemplates(),
            'class'        => 'autosubmit'
        ));

        return $this;
    }

    protected function addCheckFlagElements()
    {
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

        return $this;
    }

    protected function enumAllowedTemplates()
    {
        $object = $this->object();
        $tpl = $this->db->enumIcingaTemplates($object->getShortTableName());
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
