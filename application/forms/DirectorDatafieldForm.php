<?php

namespace Icinga\Module\Director\Forms;

use Icinga\Module\Director\Web\Form\DirectorObjectForm;
use Icinga\Web\Hook;

class DirectorDatafieldForm extends DirectorObjectForm
{
    public function setup()
    {
        $this->addElement('text', 'varname', array(
            'label'       => $this->translate('Field name'),
            'description' => $this->translate('The unique name of the field'),
            'required'    => true,
        ));

        $this->addElement('text', 'caption', array(
            'label'       => $this->translate('Caption'),
            'description' => $this->translate('The caption which should be displayed')
        ));

        $this->addElement('textarea', 'description', array(
            'label'       => $this->translate('Description'),
            'description' => $this->translate('A description about the field'),
            'rows'        => '3',
        ));

        $this->addElement('select', 'datatype', array(
            'label'         => $this->translate('Data type'),
            'description'   => $this->translate('Field type'),
            'required'      => true,
            'multiOptions'  => $this->enumDataTypes(),
            'class'         => 'autosubmit',
        ));

        if ($class = $this->object()->datatype) {
            $this->addSettings($class);
        } elseif ($class = $this->getSentValue('datatype')) {
            if ($class && array_key_exists($class, $this->enumDataTypes())) {
                $this->addSettings($class);
            }
        }
    }

    protected function addSettings($class = null)
    {
        if ($class === null) {
            if ($class = $this->getValue('datatype')) {
                $class::addSettingsFormFields($this);
            }
        } else {
            $class::addSettingsFormFields($this);
        }
    }

    public function onSuccess()
    {
        if ($class = $this->getValue('datatype')) {
            if (array_key_exists($class, $this->enumDataTypes())) {
                $this->addHidden('format', $class::getFormat());
            }
        }

        parent::onSuccess();
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

    protected function enumDataTypes()
    {
        $hooks = Hook::all('Director\\DataType');
        $enum = array(null => '- please choose -');
        foreach ($hooks as $hook) {
            $enum[get_class($hook)] = $hook->getName();
        }

        return $enum;
    }
}
