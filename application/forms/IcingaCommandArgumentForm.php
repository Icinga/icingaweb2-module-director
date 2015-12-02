<?php

namespace Icinga\Module\Director\Forms;

use Icinga\Module\Director\Objects\IcingaCommand;
use Icinga\Module\Director\Web\Form\DirectorObjectForm;

class IcingaCommandArgumentForm extends DirectorObjectForm
{
    protected $commandObject;

    public function setCommandObject(IcingaCommand $object)
    {
        $this->commandObject = $object;
        $this->setDb($object->getConnection());
        return $this;
    }

    public function setup()
    {
        $this->addHidden('command_id', $this->commandObject->id);

        $this->addElement('text', 'argument_name', array(
            'label'       => $this->translate('Argument name'),
            'description' => $this->translate('e.g. -H or --hostname, empty means "skip_key"')
        ));

        if ($this->getRequest()->getPost('argument_format') === 'expression') {
            $this->addElement('textarea', 'argument_value', array(
                'label'       => $this->translate('Value'),
                'description' => $this->translate('e.g. 5%, $hostname$, $lower$%:$upper$%'),
                'rows'        => 3
            ));
        } else {
            $this->addElement('text', 'argument_value', array(
                'label'       => $this->translate('Value'),
                'description' => $this->translate('e.g. ')
            ));
        }

        $this->addElement('select', 'argument_format', array(
            'label' => $this->translate('Value type'),
            'multiOptions' => array(
                'string'     => $this->translate('String'),
                'expression' => $this->translate('Icinga DSL')
            ),
            'class' => 'autosubmit',
        ));

        $this->addElement('text', 'set_if', array(
            'label'       => $this->translate('Condition (set_if)'),
        ));

        $this->addElement('select', 'set_if_format', array(
            'label' => $this->translate('Condition format'),
            'multiOptions' => array(
                'string'     => $this->translate('String'),
                // 'expression' => $this->translate('Icinga DSL')
            )
        ));

        $this->setButtons();
    }

    protected function beforeValidation($data = array())
    {
        if (isset($data['argument_value']) && $value = $data['argument_value']) {
            if (preg_match_all('/\$([a-z0-9_]+)\$/',  $value, $m, PREG_PATTERN_ORDER)) {
                foreach ($m[1] as $var) {
                    // $this->addCustomVariable($var);
                }
            }
        }

/*
        $this->optionalBoolean(
            'required',
            $this->translate('Required'),
            $this->translate('Whether this is a mandatory parameter')
        );
*/
    }

    protected function addCustomVariable($varname)
    {
        $a = new \Zend_Form_SubForm();
        $a->addElement('note', 'title', array(
            'label' => sprintf($this->translate('Custom Variable "%s"'), $varname),
        ));

        $a->addElement('text', 'description', array(
            'label'     => $this->translate('Description'),
            'required' => true,
        ));

        $a->addElement('text', 'default_value', array(
            'label'     => $this->translate('Default value'),
        ));
        $this->addSubForm($a, 'cv_' . $varname);
    }
}
