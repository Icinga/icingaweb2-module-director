<?php

namespace Icinga\Module\Director\Forms;

use Icinga\Module\Director\Web\Form\DirectorObjectForm;

class IcingaCommandForm extends DirectorObjectForm
{
    public function setup()
    {
        $this->addObjectTypeElement();
        if (! $this->hasObjectType()) {
            return;
        }

        $this->addElement('select', 'methods_execute', array(
            'label' => $this->translate('Command type'),
            'multiOptions' => array(
                null                 => '- please choose -',
                $this->translate('Plugin commands') => array(
                    'PluginCheck'        => 'Plugin Check Command',
                    'PluginNotification' => 'Notification Plugin Command',
                    'PluginEvent'        => 'Event Plugin Command',
                ),
                $this->translate('Internal commands') => array(
                    'IcingaCheck'        => 'Icinga Check Command',
                    'ClusterCheck'       => 'Icinga Cluster Check Command',
                    'ClusterZoneCheck'   => 'Icinga Cluster Zone Check Command',
                    'IdoCheck'           => 'Ido Check Command',
                    'RandomCheck'        => 'Random Check Command',
                    'CrlCheck'           => 'Crl Check Command',
                )
            ),
            'required'    => ! $this->isTemplate(),
            'description' => $this->translate(
                'Plugin Check commands are what you need when running checks agains'
                . ' your infrastructure. Notification commands will be used when it'
                . ' comes to notify your users. Event commands allow you to trigger'
                . ' specific actions when problems occur. Some people use them for'
                . ' auto-healing mechanisms, like restarting services or rebooting'
                . ' systems at specific thresholds'
            ),
            'class'       => 'autosubmit'
        ));

        $this->addElement('text', 'object_name', array(
            'label'       => $this->translate('Command name'),
            'required'    => true,
            'description' => $this->translate('Identifier for the Icinga command you are going to create')
        ));

        $this->addImportsElement();

        $this->addElement('text', 'command', array(
            'label'       => $this->translate('Command'),
            'required'    => ! $this->isTemplate(),
            'description' => $this->translate(
                'The command Icinga should run. Absolute paths are accepted as provided,'
                . ' relative paths are prefixed with "PluginDir + ", similar Constant prefixes are allowed.'
                . ' Spaces will lead to separation of command path and standalone arguments. Please note that'
                . ' this means that we do not support spaces in plugin names and paths right now.'
            )
        ));

        $this->addElement('text', 'timeout', array(
            'label' => $this->translate('Timeout'),
            'description' => $this->translate(
                'Optional command timeout. Allowed values are seconds or durations postfixed with a'
                . ' specific unit (e.g. 1m or also 3m 30s).'
            )
        ));
        $this->addDisabledElement();

        $this->setButtons();
    }
}
