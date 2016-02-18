<?php

namespace Icinga\Module\Director\Forms;

use Icinga\Application\Hook;
use Icinga\Module\Director\Objects\ImportSource;
use Icinga\Module\Director\Web\Form\DirectorObjectForm;

class ImportRowModifierForm extends DirectorObjectForm
{
    protected $source;

    public function setup()
    {
        $this->addHidden('source_id', $this->source->id);
        $this->addHidden('priority', 1);

        $this->addElement('text', 'property_name', array(
            'label' => $this->translate('Property'),
            'description' => $this->translate('This must be an import source column'),
            'required'    => true,
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
            } elseif ($class = $this->object()->provider_class) {
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
    }

    protected function enumModifiers()
    {
        $hooks = Hook::all('Director\\PropertyModifier');
        $enum = array();
        foreach ($hooks as $hook) {
            $enum[get_class($hook)] = $hook->getName();
        }

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
