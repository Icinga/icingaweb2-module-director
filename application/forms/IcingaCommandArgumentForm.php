<?php

namespace Icinga\Module\Director\Forms;

use Icinga\Module\Director\Objects\IcingaCommand;
use Icinga\Module\Director\Objects\IcingaCommandArgument;
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

        $this->addElement('select', 'argument_format', array(
            'label' => $this->translate('Value type'),
            'multiOptions' => array(
                'string'     => $this->translate('String'),
                'expression' => $this->translate('Icinga DSL')
            ),
            'description' => $this->translate(
                'Whether the argument value is a string (allowing macros like $host$)'
                . ' or an Icinga DSL lambda function (will be enclosed with {{ ... }}'
            ),
            'class' => 'autosubmit',
        ));

        if ($this->getSentOrObjectValue('argument_format') === 'expression') {
            $this->addElement('textarea', 'argument_value', array(
                'label'       => $this->translate('Value'),
                'description' => $this->translate(
                    'And Icinga DSL expression, e.g.: var cmd = macro("$cmd$");'
                    . ' return typeof(command) == String ...'
                ),
                'rows'        => 3
            ));
        } else {
            $this->addElement('text', 'argument_value', array(
                'label'       => $this->translate('Value'),
                'description' => $this->translate(
                    'e.g. 5%, $hostname$, $lower$%:$upper$%'
                )
            ));
        }

        $this->addElement('text', 'sort_order', array(
            'label'       => $this->translate('Position'),
            'description' => $this->translate(
                'Leave empty for non-positional arguments. Can be a positive or'
                . ' negative number and influences argument ordering'
            )
        ));

        $this->addElement('select', 'set_if_format', array(
            'label' => $this->translate('Condition format'),
            'multiOptions' => array(
                'string'     => $this->translate('String'),
                'expression' => $this->translate('Icinga DSL')
            ),
            'description' => $this->translate(
                'Whether the set_if parameter is a string (allowing macros like $host$)'
                . ' or an Icinga DSL lambda function (will be enclosed with {{ ... }}'
            )
        ));

        $this->addElement('text', 'set_if', array(
            'label'       => $this->translate('Condition (set_if)'),
            'description' => $this->translate(
                'Only set this parameter if the argument value resolves to a'
                . ' numeric value. String values are not supported'
            )
        ));

        $this->addBoolean('repeat_key', array(
            'label'       => $this->translate('Repeat key'),
            'description' => $this->translate(
                'Whether this parameter should be repeated when multiple values'
                . ' (read: array) are given'
            )
        ));

        $this->addBoolean('required', array(
            'label'       => $this->translate('Required'),
            'required'    => false,
            'description' => $this->translate('Whether this argument should be required')
        ));

        $this->setButtons();
    }


    public function onSuccess()
    {
        $object = $this->object();
        $cmd = $this->commandObject;
        if ($object->hasBeenModified()) {
            $cmd->arguments()->set(
                $object->argument_name,
                IcingaCommandArgument::create($this->getValues(), $this->db)
            );
            $msg = sprintf(
                $this->translate('The argument %s has successfully been stored'),
                $object->argument_name
            );
            $object->store($this->db);
        } else {
            $this->setHttpResponseCode(304);
            $msg = $this->translate('No action taken, object has not been modified');
        }
        $this->setSuccessUrl(
            'director/director/command',
            array('name' => $cmd->argument_name)
        );

        $this->redirectOnSuccess($msg);
    }
}
