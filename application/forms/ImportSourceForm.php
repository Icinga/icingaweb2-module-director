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
            'multiOptions' => $this->enumSourceTypes(),
            'class'       => 'autosubmit'
        ));

        // TODO: Form needs to provide a better way for doing this
        if (isset($_POST['provider_class'])) {
            $class = $_POST['provider_class'];
            if ($class && array_key_exists($class, $this->enumSourceTypes())) {
                $this->addSettings($class);
            }
        }
    }

    protected function addSettings($class = null)
    {
        if ($class === null) {
            if ($class = $this->getValue('provider_class')) {
                $class::addSettingsFormFields($this);
            }
        } else {
            $class::addSettingsFormFields($this);
        }
    }

    public function loadObject($id)
    {
        
        parent::loadObject($id);
        $this->addSettings();
        foreach ($this->object()->getSettings() as $key => $val) {
            if ($el = $this->getElement($key)) {
                $el->setValue($val);
            }
        }
        $this->moveSubmitToBottom();

        return $this;
    }

    public function onSuccess()
    {
/*
        $this->getElement('owner')->setValue(
            self::username()
        );
*/
        parent::onSuccess();
    }

    protected function enumSourceTypes()
    {
        $hooks = Hook::all('Director\\ImportSource');
        $enum = array(null => '- please choose -');
        foreach ($hooks as $hook) {
            $enum[get_class($hook)] = $hook->getName();
        }

        return $enum;
    }
}
