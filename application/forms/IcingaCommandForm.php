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

        $nameLabel = $this->isTemplate()
            ? $this->translate('Name')
            : $this->translate('Command name');

        $this->addElement('text', 'object_name', array(
            'label'       => $nameLabel,
            'required'    => true,
            'description' => $this->translate('Identifier for the Icinga command you are going to create')
        ));

        $this->addImportsElement(false);

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

        $descIsString = [
            $this->translate('Render the command as a plain string instead of an array.'),
            $this->translate('If enabled you can not define arguments.'),
            $this->translate('Disabled by default, and should only be used in rare cases.'),
            $this->translate('WARNING, this can allow shell script injection via custom variables used in command.'),
        ];

        $this->addBoolean(
            'is_string',
            array(
                'label'       => $this->translate('Render as string'),
                'description' => join(' ', $descIsString),
            )
        );

        $this->addDisabledElement();
        $this->addZoneSection();
        $this->setButtons();
    }

    protected function addZoneSection()
    {
        $this->addZoneElement(true);

        $elements = array(
            'zone_id',
        );
        $this->addDisplayGroup($elements, 'clustering', array(
            'decorators' => array(
                'FormElements',
                array('HtmlTag', array('tag' => 'dl')),
                'Fieldset',
            ),
            'order' => self::GROUP_ORDER_CLUSTERING,
            'legend' => $this->translate('Zone settings')
        ));

        return $this;
    }

    protected function enumAllowedTemplates()
    {
        $object = $this->object();
        $tpl = $this->db->enum($object->getTableName());
        if (empty($tpl)) {
            return array();
        }

        $id = $object->get('id');

        if (array_key_exists($id, $tpl)) {
            unset($tpl[$id]);
        }

        if (empty($tpl)) {
            return array();
        }

        $tpl = array_combine($tpl, $tpl);
        return $tpl;
    }
}
