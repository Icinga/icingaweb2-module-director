<?php

namespace Icinga\Module\Director\Forms;

use Icinga\Module\Director\Web\Form\DirectorObjectForm;
use Icinga\Web\Hook;

class DirectorDatafieldForm extends DirectorObjectForm
{
    public function setup()
    {
        $this->addElement('text', 'varname', array(
            'required' => true,
            'label' => $this->translate('Field name'),
            'description' => $this->translate('The unique name of the field')
        ));

        $this->addElement('text', 'caption', array(
            'label' => $this->translate('Caption'),
            'description' => $this->translate('The caption which should be displayed')
        ));

        $this->addElement('textarea', 'description', array(
            'label' => $this->translate('Description'),
            'description' => $this->translate('A description about the field')
        ));

        $this->addElement('select', 'datatype', array(
            'label'         => $this->translate('Data type'),
            'description'   => $this->translate('Field type'),
            'required'      => true,
            'multiOptions'  => $this->enumDataTypes(),
            'class'         => 'autosubmit'
        ));

        $this->addElement('hidden', 'format',
            array('decorators' => array('ViewHelper'))
        );

        if (isset($_POST['datatype'])) {
            $class = $_POST['datatype'];
            if ($class && array_key_exists($class, $this->enumDataTypes())) {
                $this->getElement('format')->setValue($class::getFormat());
            }
        }
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
