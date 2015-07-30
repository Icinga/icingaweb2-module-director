<?php

namespace Icinga\Module\Director\Forms;

use Icinga\Module\Director\Web\Form\DirectorObjectForm;

class IcingaCommandArgumentForm extends DirectorObjectForm
{
    public function setup()
    {
        $this->addElement('select', 'command_id', array(
            'label'        => $this->translate('Check command'),
            'description'  => $this->translate('Check command definition'),
            'multiOptions' => $this->optionalEnum($this->db->enumCommands())
        ));

        $this->addElement('text', 'argument_name', array(
            'label'       => $this->translate('Argument name'),
            'required'    => true,
            'description' => $this->translate('e.g. -H or --hostname, empty means "skip_key"')
        ));

        $this->addElement('text', 'argument_value', array(
            'label' => $this->translate('Value'),
            'description' => $this->translate('e.g. 5%, $hostname$, $lower$%:$upper$%')
        ));

        $this->addHidden('value_format', 'string'); // expression, json?
    }


    protected function beforeValidation($data = array())
    {
        if (isset($data['argument_value']) && $value = $data['argument_value']) {
            if (preg_match_all('/\$([a-z0-9_]+)\$/',  $value, $m, PREG_PATTERN_ORDER)) {
                foreach ($m[1] as $var) {
                    $this->addCustomVariable($var);
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
