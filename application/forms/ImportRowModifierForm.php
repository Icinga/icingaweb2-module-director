<?php

namespace Icinga\Module\Director\Forms;

use Exception;
use Icinga\Application\Hook;
use Icinga\Exception\ConfigurationError;
use Icinga\Module\Director\Hook\ImportSourceHook;
use Icinga\Module\Director\Hook\PropertyModifierHook;
use Icinga\Module\Director\Import\Import;
use Icinga\Module\Director\Objects\ImportSource;
use Icinga\Module\Director\Web\Form\DirectorObjectForm;

class ImportRowModifierForm extends DirectorObjectForm
{
    /** @var  ImportSource */
    protected $source;

    /** @var  ImportSourceHook */
    protected $importSource;

    public function setup()
    {
        $this->addHidden('source_id', $this->source->id);
        $this->addHidden('priority', 1);

        $this->addElement('select', 'property_name', array(
            'label'        => $this->translate('Property'),
            'description'  => $this->translate('This must be an import source column (property)'),
            'multiOptions' => $this->optionalEnum($this->enumSourceColumns()),
            'required'     => true,
        ));

        $this->addElement('text', 'target_property', array(
            'label'        => $this->translate('Target property'),
            'description'  => $this->translate(
                'You might want to write the modified value to another (new) property.'
                . ' This property name can be defined here, the original property would'
                . ' remain unmodified. Please leave this blank in case you just want to'
                . ' modify the value of a specific property'
            ),
        ));

        $this->addElement('textarea', 'description', array(
            'label'       => $this->translate('Description'),
            'description' => $this->translate(
                'An extended description for this Import Row Modifier. This should explain'
                . " it's purpose and why it has been put in place at all."
            ),
            'rows'        => '3',
        ));

        $error = false;
        try {
            $mods = $this->enumModifiers();
        } catch (Exception $e) {
            $error = $e->getMessage();
            $mods = $this->optionalEnum(array());
        }
        
        $this->addElement('select', 'provider_class', array(
            'label'        => $this->translate('Modifier'),
            'required'     => true,
            'description'  => $this->translate(
                'A property modifier allows you to modify a specific property at import time'
            ),
            'multiOptions' => $this->optionalEnum($mods),
            'class'        => 'autosubmit',
        ));
        if ($error) {
            $this->getElement('provider_class')->addError($error);
        }

        try {
            if ($class = $this->getSentValue('provider_class')) {
                if ($class && array_key_exists($class, $mods)) {
                    $this->addSettings($class);
                }
            } elseif ($class = $this->object()->get('provider_class')) {
                $this->addSettings($class);
            }

            // TODO: next line looks like obsolete duplicate code to me
            $this->addSettings();
        } catch (Exception $e) {
            $this->getElement('provider_class')->addError($e->getMessage());
        }

        foreach ($this->object()->getSettings() as $key => $val) {
            if ($el = $this->getElement($key)) {
                $el->setValue($val);
            }
        }

        $this->setButtons();
    }

    protected function enumSourceColumns()
    {
        $columns = array_merge(
            $this->getImportSource()->listColumns(),
            $this->source->listModifierTargetProperties()
        );

        $columns = array_combine($columns, $columns);
        return $columns;
    }

    protected function getImportSource()
    {
        if ($this->importSource === null) {
            $this->importSource = ImportSourceHook::loadByName(
                $this->source->get('source_name'),
                $this->db
            );
        }

        return $this->importSource;
    }

    protected function enumModifiers()
    {
        /** @var PropertyModifierHook[] $hooks */
        $hooks = Hook::all('Director\\PropertyModifier');
        $enum = array();
        foreach ($hooks as $hook) {
            $enum[get_class($hook)] = $hook->getName();
        }

        asort($enum);

        return $enum;
    }

    protected function addSettings($class = null)
    {
        if ($class === null) {
            $class = $this->getValue('provider_class');
        }

        if ($class !== null) {
            if (! class_exists($class)) {
                throw new ConfigurationError(
                    'The hooked class "%s" for this property modifier does no longer exist',
                    $class
                );
            }

            $class::addSettingsFormFields($this);
        }
    }

    public function setSource(ImportSource $source)
    {
        $this->source = $source;
        return $this;
    }
}
