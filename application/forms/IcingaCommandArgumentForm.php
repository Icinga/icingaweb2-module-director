<?php

namespace Icinga\Module\Director\Forms;

use Icinga\Module\Director\Objects\IcingaCommand;
use Icinga\Module\Director\Web\Form\DirectorObjectForm;

class IcingaCommandArgumentForm extends DirectorObjectForm
{
    /** @var  IcingaCommand */
    protected $commandObject;

    public function setCommandObject(IcingaCommand $object)
    {
        $this->commandObject = $object;
        $this->setDb($object->getConnection());
        return $this;
    }

    public function setup()
    {
        $this->addHidden('command_id', $this->commandObject->get('id'));

        $this->addElement('text', 'argument_name', array(
            'label'       => $this->translate('Argument name'),
            'filters'     => array('StringTrim'),
            'description' => $this->translate('e.g. -H or --hostname, empty means "skip_key"')
        ));

        $this->addElement('text', 'description', array(
            'label'       => $this->translate('Description'),
            'description' => $this->translate('Description of the argument')
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
                    'An Icinga DSL expression, e.g.: var cmd = macro("$cmd$");'
                    . ' return typeof(command) == String ...'
                ),
                'rows'        => 3
            ));
        } else {
            $this->addElement('text', 'argument_value', array(
                'label'       => $this->translate('Value'),
                'description' => $this->translate(
                    'e.g. 5%, $host.name$, $lower$%:$upper$%'
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
            ),
            'class' => 'autosubmit',
        ));

        if ($this->getSentOrObjectValue('set_if_format') === 'expression') {
            $this->addElement('textarea', 'set_if', array(
                'label'       => $this->translate('Condition (set_if)'),
                'description' => $this->translate(
                    'An Icinga DSL expression that returns a boolean value, e.g.: var cmd = bool(macro("$cmd$"));'
                    . ' return cmd ...'
                ),
                'rows'        => 3
            ));
        } else {
            $this->addElement('text', 'set_if', array(
                'label'       => $this->translate('Condition (set_if)'),
                'description' => $this->translate(
                    'Only set this parameter if the argument value resolves to a'
                    . ' numeric value. String values are not supported'
                )
            ));
        }

        $this->addBoolean('repeat_key', array(
            'label'       => $this->translate('Repeat key'),
            'description' => $this->translate(
                'Whether this parameter should be repeated when multiple values'
                . ' (read: array) are given'
            )
        ));

        $this->addBoolean('skip_key', array(
            'label'       => $this->translate('Skip key'),
            'description' => $this->translate(
                'Whether the parameter name should not be passed to the command.'
                . ' Per default, the parameter name (e.g. -H) will be appended,'
                . ' so no need to explicitly set this to "No".'
            )
        ));

        $this->addBoolean('required', array(
            'label'       => $this->translate('Required'),
            'required'    => false,
            'description' => $this->translate('Whether this argument should be required')
        ));

        $this->setButtons();
    }

    protected function deleteObject($object)
    {
        $cmd = $this->commandObject;

        $msg = sprintf(
            '%s argument "%s" has been removed',
            $this->translate($this->getObjectShortClassName()),
            $object->argument_name
        );

        $url = $this->getSuccessUrl()->without('argument_id');

        $cmd->arguments()->remove($object->argument_name);
        if ($cmd->store()) {
            $this->setSuccessUrl($url);
        }

        $this->redirectOnSuccess($msg);
    }

    public function onSuccess()
    {
        $object = $this->object();
        $cmd = $this->commandObject;
        if ($object->get('argument_name') === null) {
            $object->set('skip_key', true);
            $object->set('argument_name', $cmd->getNextSkippableKeyName());
        }

        if ($object->hasBeenModified()) {
            $cmd->arguments()->set(
                $object->get('argument_name'),
                $object
            );
            $msg = sprintf(
                $this->translate('The argument %s has successfully been stored'),
                $object->get('argument_name')
            );
            $cmd->store($this->db);
        } else {
            if ($this->isApiRequest()) {
                $this->setHttpResponseCode(304);
            }
            $msg = $this->translate('No action taken, object has not been modified');
        }
        $this->setSuccessUrl(
            'director/command/arguments',
            [
                'argument_id' => $object->get('id'),
                'name' => $cmd->getObjectName()
            ]
        );

        $this->redirectOnSuccess($msg);
    }
}
