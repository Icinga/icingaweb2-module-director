<?php

namespace Icinga\Module\Director\Forms;

use Icinga\Module\Director\Hook\ImportSourceHook;
use Icinga\Module\Director\Objects\ImportSource;
use Icinga\Module\Director\Web\Form\DirectorObjectForm;
use Icinga\Web\Hook;

class ImportSourceForm extends DirectorObjectForm
{
    public function setup()
    {
        $this->addElement('text', 'source_name', array(
            'label'       => $this->translate('Import source name'),
            'description' => $this->translate(
                'A short name identifying this import source. Use something meaningful,'
                . ' like "Hosts from Puppet", "Users from Active Directory" or similar'
            ),
            'required'    => true,
        ));

        $this->addElement('textarea', 'description', array(
            'label'       => $this->translate('Description'),
            'description' => $this->translate(
                'An extended description for this Import Source. This should explain'
                . " what kind of data you're going to import from this source."
            ),
            'rows'        => '3',
        ));

        $this->addElement('select', 'provider_class', array(
            'label'        => $this->translate('Source Type'),
            'required'     => true,
            'multiOptions' => $this->optionalEnum($this->enumSourceTypes()),
            'description'  => $this->translate(
                'These are different data providers fetching data from various sources.'
                . ' You didn\'t find what you\'re looking for? Import sources are implemented'
                . ' as a hook in Director, so you might find (or write your own) Icinga Web 2'
                . ' module fetching data from wherever you want'
            ),
            'class'        => 'autosubmit'
        ));

        $this->addSettings();
        $this->setButtons();
    }

    public function getSentOrObjectSetting($name, $default = null)
    {
        if ($this->hasObject()) {
            $value = $this->getSentValue($name);
            if ($value === null) {
                /** @var ImportSource $object */
                $object = $this->getObject();

                return $object->getSetting($name, $default);
            } else {
                return $value;
            }
        } else {
            return $this->getSentValue($name, $default);
        }
    }

    protected function addSettings()
    {
        if (! ($class = $this->getProviderClass())) {
            return;
        }

        $defaultKeyCol = $this->getDefaultKeyColumnName();

        $this->addElement('text', 'key_column', array(
            'label' => $this->translate('Key column name'),
            'description' => $this->translate(
                'This must be a column containing unique values like hostnames. Unless otherwise'
                . ' specified this will then be used as the object_name for the syncronized'
                . ' Icinga object. Especially when getting started with director please make'
                . ' sure to strictly follow this rule. Duplicate values for this column on different'
                . ' rows will trigger a failure, your import run will not succeed. Please pay attention'
                . ' when synching services, as "purge" will only work correctly with a key_column'
                . ' corresponding to host!name. Check the "Combine" property modifier in case your'
                . ' data source cannot provide such a field'
            ),
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

        if (! class_exists($class)) {
            return null;
        }

        return $class::getDefaultKeyColumnName();
    }

    protected function getProviderClass()
    {
        if ($this->hasBeenSent()) {
            $class = $this->getRequest()->getPost('provider_class');
        } else {
            if (! ($class = $this->object()->get('provider_class'))) {
                return null;
            }
        }

        return $class;
    }

    public function onSuccess()
    {
        if (! $this->getValue('key_column')) {
            if ($default = $this->getDefaultKeyColumnName()) {
                $this->setElementValue('key_column', $default);
                $this->object()->set('key_column', $default);
            }
        }

        parent::onSuccess();
    }

    protected function enumSourceTypes()
    {
        /** @var ImportSourceHook[] $hooks */
        $hooks = Hook::all('Director\\ImportSource');

        $enum = array();
        foreach ($hooks as $hook) {
            $enum[get_class($hook)] = $hook->getName();
        }
        asort($enum);

        return $enum;
    }
}
