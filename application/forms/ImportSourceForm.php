<?php

namespace Icinga\Module\Director\Forms;

use Icinga\Module\Director\Web\Form\DirectorObjectForm;
use Icinga\Web\Hook;

class ImportSourceForm extends DirectorObjectForm
{
    public function setup()
    {
        $this->addElement('text', 'source_name', array(
            'label' => $this->translate('Import source name'),
            'required'    => true,
        ));

        $this->addElement('select', 'provider_class', array(
            'label'       => $this->translate('Source Type'),
            'required'    => true,
            'multiOptions' => $this->optionalEnum($this->enumSourceTypes()),
            'class'       => 'autosubmit'
        ));

        $this->addSettings();
        $this->setButtons();
    }

    protected function addSettings($class = null)
    {
        if (! ($class = $this->getProviderClass())) {
            return;
        }

        $defaultKeyCol = $this->getDefaultKeyColumnName();

        $this->addElement('text', 'key_column', array(
            'label' => $this->translate('Key column name'),
            'description' => $this->translate('This must be a column containing unique values like hostnames'),
            'placeholder' => $defaultKeyCol,
            'required'    => $defaultKeyCol === null,
        ));

        if (array_key_exists($class, $this->enumSourceTypes())) {
            $class::addSettingsFormFields($this);
            foreach ($this->object()->getSettings() as $key => $val) {
                if ($el = $this->getElement($key)) {
                    $el->setValue($val);
                }
            }
        }
    }

    protected function getDefaultKeyColumnName()
    {
        if (! ($class = $this->getProviderClass())) {
            return null;
        }

        return $class::getDefaultKeyColumnName();
    }

    protected function getProviderClass()
    {
        if ($this->hasBeenSent()) {
            $class = $this->getRequest()->getPost('provider_class');
        } else {
            if (! ($class = $this->object()->provider_class)) {
                return;
            }
        }

        return $class;
    }

    public function onSuccess()
    {
        if (! $this->getValue('key_column')) {
            if ($default = $this->getDefaultKeyColumnName()) {
                $this->setElementValue('key_column', $default);
                $this->object()->key_column = $default;
            }
        }

        parent::onSuccess();
    }

    protected function enumSourceTypes()
    {
        $hooks = Hook::all('Director\\ImportSource');
        $enum = array();
        foreach ($hooks as $hook) {
            $enum[get_class($hook)] = $hook->getName();
        }

        return $enum;
    }
}
