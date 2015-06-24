<?php

namespace Icinga\Module\Director\Forms;

use Icinga\Module\Director\Web\Form\DirectorObjectForm;

class IcingaCommandForm extends DirectorObjectForm
{
    public function setup()
    {
        $this->addElement('select', 'methods_execute', array(
            'label' => $this->translate('Command type'),
            'description' => $this->translate('Whether this should be a template'),
            'multiOptions' => array(
                null                 => '- please choose -',
                'PluginCheck'        => 'Plugin Check Command',
                'PluginNotification' => 'Notification Plugin Command',
                'PluginEvent'        => 'Event Plugin Command',
                'IcingaCheck'        => 'Icinga Check Command',
                'ClusterCheck'       => 'Icinga Cluster Command',
                'RandomCheck'        => 'Random Check Command',
                'ClusterZoneCheck'   => 'Icinga Cluster Zone Check Command',
                'CrlCheck'           => 'Crl Check Command',
            ),
            'class' => 'autosubmit'
        ));

        $this->addElement('text', 'object_name', array(
            'label'       => $this->translate('Command name'),
            'required'    => true,
            'description' => $this->translate('Identifier for the Icinga command you are going to create')
        ));

        $this->addElement('text', 'timeout', array(
            'label' => $this->translate('Timeout'),
            'description' => $this->translate('Optional command timeout')
        ));

        $this->addElement('select', 'zone_id', array(
            'label' => $this->translate('Cluster Zone'),
            'description' => $this->translate('Provide this command only to this specific Icinga cluster zone')
        ));

        $this->addElement('select', 'object_type', array(
            'label' => $this->translate('Object type'),
            'required'     => true,
            'description'  => $this->translate('Whether to create a command template or a command object'),
            'multiOptions' => $this->optionalEnum(array(
                'object'   => $this->translate('Command object'),
                'template' => $this->translate('Command template'),
            ))
        ));
    }
}
