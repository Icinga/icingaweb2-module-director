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

        $this->addElement('text', 'key_column', array(
            'label' => $this->translate('Key column name'),
            'description' => $this->translate('This must be a column containing unique values like hostnames'),
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
        if ($this->hasBeenSent()) {
            $class = $this->getRequest()->getPost('provider_class');
        } else {
            if (! ($class = $this->object()->provider_class)) {
                return;
            }
        }

        if (array_key_exists($class, $this->enumSourceTypes())) {
            $class::addSettingsFormFields($this);
            foreach ($this->object()->getSettings() as $key => $val) {
                if ($el = $this->getElement($key)) {
                    $el->setValue($val);
                }
            }
        }
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
