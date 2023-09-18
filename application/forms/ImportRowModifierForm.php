<?php

namespace Icinga\Module\Director\Forms;

use Exception;
use Icinga\Application\Hook;
use Icinga\Exception\ConfigurationError;
use Icinga\Module\Director\Hook\ImportSourceHook;
use Icinga\Module\Director\Hook\PropertyModifierHook;
use Icinga\Module\Director\Objects\ImportSource;
use Icinga\Module\Director\Web\Form\DirectorObjectForm;
use RuntimeException;

class ImportRowModifierForm extends DirectorObjectForm
{
    /** @var  ImportSource */
    protected $source;

    /** @var  ImportSourceHook */
    protected $importSource;

    /**
     * @throws \Zend_Form_Exception
     */
    public function setup()
    {
        $this->addHidden('source_id', $this->source->id);

        $this->addElement('text', 'property_name', array(
            'label'        => $this->translate('Property'),
            'description'  => $this->translate(
                'Please start typing for a list of suggestions. Dots allow you to access nested'
                . ' properties: column.some.key. Such nested properties cannot be modified in-place,'
                . ' but you can store the modified value to a new "target property"'
            ),
            'required'     => true,
            'class'        => 'autosubmit director-suggest',
            'data-suggestion-context' => 'importsourceproperties!' . $this->source->id,
        ));

        $this->addElement('text', 'target_property', [
            'label'        => $this->translate('Target property'),
            'description'  => $this->translate(
                'You might want to write the modified value to another (new) property.'
                . ' This property name can be defined here, the original property would'
                . ' remain unmodified. Please leave this blank in case you just want to'
                . ' modify the value of a specific property'
            ),
        ]);

        $this->addElement('textarea', 'description', [
            'label'       => $this->translate('Description'),
            'description' => $this->translate(
                'An extended description for this Import Row Modifier. This should explain'
                . " it's purpose and why it has been put in place at all."
            ),
            'rows'        => '3',
        ]);

        $error = false;
        try {
            $mods = $this->enumModifiers();
        } catch (Exception $e) {
            $error = $e->getMessage();
            $mods = $this->optionalEnum([]);
        }
        $this->addElement('YesNo', 'use_filter', [
            'label'        => $this->translate('Set based on filter'),
            'ignore'       => true,
            'class'        => 'autosubmit',
            'required'     => true,
        ]);

        if ($this->hasBeenSent()) {
            $useFilter = $this->getSentValue('use_filter');
            if ($useFilter === null) {
                $this->setElementValue('use_filter', $useFilter = 'n');
            }
        } elseif ($object = $this->getObject()) {
            $expression = $object->get('filter_expression');
            $useFilter = ($expression === null || strlen($expression) === 0) ? 'n' : 'y';
            $this->setElementValue('use_filter', $useFilter);
        } else {
            $this->setElementValue('use_filter', $useFilter = 'n');
        }

        if ($useFilter === 'y') {
            $this->addElement('text', 'filter_expression', [
                'label'       => $this->translate('Filter Expression'),
                'description' => $this->translate(
                    'This allows to filter for specific parts within the given source expression.'
                    . ' You are allowed to refer all imported columns. Examples: host=www* would'
                    . ' set this property only for rows imported with a host property starting'
                    . ' with "www". Complex example: host=www*&!(address=127.*|address6=::1).'
                    . ' Please note, that CIDR notation based matches are also supported: '
                    . ' address=192.0.2.128/25| address=2001:db8::/32| address=::ffff:192.0.2.0/96'
                ),
                'required'    => true,
                // TODO: validate filter
            ]);
        }

        $this->addElement('select', 'provider_class', [
            'label'        => $this->translate('Modifier'),
            'required'     => true,
            'description'  => $this->translate(
                'A property modifier allows you to modify a specific property at import time'
            ),
            'multiOptions' => $this->optionalEnum($mods),
            'class'        => 'autosubmit',
        ]);
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

    public function getSetting($name, $default = null)
    {
        if ($this->hasBeenSent()) {
            $value = $this->getSentValue($name);
            if ($value !== null) {
                return $value;
            }
        }
        if ($this->isNew()) {
            $value = $this->getElement($name)->getValue();
            if ($value === null) {
                return $default;
            }

            return $value;
        }

        return $this->object()->getSetting($name, $default);
    }

    /**
     * @return ImportSourceHook
     * @throws ConfigurationError
     */
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
        $enum = [];
        foreach ($hooks as $hook) {
            $enum[get_class($hook)] = $hook->getName();
        }

        asort($enum);

        return $enum;
    }

    /**
     * @param null $class
     */
    protected function addSettings($class = null)
    {
        if ($class === null) {
            $class = $this->getValue('provider_class');
        }

        if ($class !== null) {
            if (! class_exists($class)) {
                throw new RuntimeException(sprintf(
                    'The hooked class "%s" for this property modifier does no longer exist',
                    $class
                ));
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
