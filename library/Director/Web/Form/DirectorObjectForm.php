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

    protected $objectType = 'object';

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

        $inherited = $object->getInheritedProperties();
        $origins   = $object->getOriginsProperties();

        foreach ($props as $k => $v) {
            if (property_exists($inherited, $k)) {
                $this->setElementValue($k, $v, $inherited->$k, $origins->$k);
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
                    $vars[substr($key, 4)] = $value;
                    $handled[$key] = true;
                }

                if (substr($key, 0, 8) === '_newvar_') {
                    $newvar[substr($key, 8)] = $value;
                    $handled[$key] = true;
                }
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

        $fields   = $object->getResolvedFields();
        $inherits = $object->getInheritedVars();
        $origins  = $object->getOriginsVars();

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

        // Additional vars
        foreach ($vars as $key => $value) {
            // Did we already create a field for this var? Then skip it:
            if (array_key_exists($key, $fields)) {
                continue;
            }

            // Show inheritance information in case we inherited this var:
            if (isset($inherited->$key)) {
                $this->addCustomVar($key, $value, $inherited->$key, $origins->$key);            
            } else {
                $this->addCustomVar($key, $value);            
            }

        }

        if ($object->isTemplate()) {
            $this->addHtml('<h3>Add a custom variable</h3>');
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
    }

    public function setObjectType($type)
    {
        $this->objectType = $type;
        return $this;
    }

    protected function addField($field, $value = null, $inherited = null, $inheritedFrom = null)
    {
        $datafield = DirectorDatafield::load($field->datafield_id, $this->getDb());
        $datatype = new $datafield->datatype;
        $datatype->setSettings($datafield->getSettings());

        $name = 'var_' . $datafield->varname;
        $el = $datatype->getFormElement($name, $this);

        $el->setLabel($datafield->caption);
        $el->setDescription($datafield->description);

        if ($field->is_required === 'y' && ! $this->isTemplate()) {
            $el->setRequired(true);
        }

        $this->addElement($el);
        $this->setElementValue($name, $value, $inherited, $inheritedFrom);

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

            $msg = sprintf(
                $object->hasBeenLoadedFromDb()
                ? $this->translate('The %s has successfully been stored')
                : $this->translate('A new %s has successfully been created'),
                $this->translate($this->getObjectName())
            );
            $object->store($this->db);
        } else {
            $msg = $this->translate('No action taken, object has not been modified');
        }
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

    protected function onRequest()
    {
        $object = $this->object();
        $values = array();

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
            if (! $object->hasBeenLoadedFromDb()) {
                $object->object_type = $this->objectType;
            }
            $this->handleImports($object, $values);
            $this->handleCustomVars($object, $values);
            $this->handleGroups($object, $values);
            $this->handleRanges($object, $values);
        }
       
        $this->handleProperties($object, $values);

        $this->moveSubmitToBottom();
    }

    public function loadObject($id)
    {
        $class = $this->getObjectClassname();
        $this->object = $class::load($id, $this->db);

        // TODO: hmmmm...
        if (! is_array($id)) {
            $this->addHidden('id');
        }

        return $this;
    }

    protected function addCustomVar($key, $value, $inherited = null, $inheritedFrom = null)
    {
        $label = 'vars.' . $key;
        $key = 'var_' . $key;
        $this->addElement('text', $key, array('label' => $label));
        $this->setElementValue($key, $value, $inherited, $inheritedFrom);
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

    protected function addCheckExecutionElements()
    {
        $this->addHtml('<h3>Check execution</h3>');

        $this->addElement('select', 'check_command_id', array(
            'label' => $this->translate('Check command'),
            'description'  => $this->translate('Check command definition'),
            'multiOptions' => $this->optionalEnum($this->db->enumCheckCommands())
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
