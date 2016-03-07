<?php

namespace Icinga\Module\Director\Forms;

use Icinga\Module\Director\Web\Form\DirectorObjectForm;

class IcingaServiceForm extends DirectorObjectForm
{
    public function setup()
    {
        $this->addObjectTypeElement();
        if (! $this->hasObjectType()) {
            return;
        }

        $this->addElement('text', 'object_name', array(
            'label'       => $this->translate('Name'),
            'required'    => true,
            'description' => $this->translate('Name for the Icinga object you are going to create')
        ));

        // TODO: Should not be 'object' on new empty form:
        if ($this->isObject()) {
            $this->addElement('select', 'host_id', array(
                'label'       => $this->translate('Host'),
                'required'    => true,
                'multiOptions' => $this->optionalEnum($this->enumHostsAndTemplates()),
                'description' => $this->translate('Choose the host this single service should be assigned to')
            ));
        }

        $this->addElement('extensibleSet', 'groups', array(
            'label'        => $this->translate('Groups'),
            'multiOptions' => $this->optionallyAddFromEnum($this->enumServicegroups()),
            'positional'   => false,
            'description'  => $this->translate(
                'Service groups that should be directly assigned to this service. Servicegroups can be useful'
                . ' for various reasons. They are helpful to provided service-type specific view in Icinga Web 2,'
                . ' either for custom dashboards or as an instrument to enforce restrictior. Service groups'
                . ' can be directly assigned to single services or to service templates.'
            )
        ));

        $this->addImportsElement();
        $this->addDisabledElement();

        $elements = array(
            'object_type',
            'object_name',
            'display_name',
            'imports',
            'host_id',
            'groups',
            'disabled',
        );
        $this->addDisplayGroup($elements, 'object_definition', array(
            'decorators' => array(
                'FormElements',
                array('HtmlTag', array('tag' => 'dl')),
                'Fieldset',
            ),
            'order' => 20,
            'legend' => $this->translate('Service properties')
        ));

        $this->addCheckCommandElements();

        if ($this->isTemplate()) {
            $this->addCheckExecutionElements();
        }

        if ($this->isTemplate()) {
            $this->optionalBoolean(
                'use_agent',
                $this->translate('Run on agent'),
                $this->translate('Whether the check commmand for this service should be executed on the Icinga agent')
            );
            $this->addZoneElement();

            $elements = array(
                'use_agent',
                'zone_id',
            );
            $this->addDisplayGroup($elements, 'clustering', array(
                'decorators' => array(
                    'FormElements',
                    array('HtmlTag', array('tag' => 'dl')),
                    'Fieldset',
                ),
                'order' => 40,
                'legend' => $this->translate('Icinga Agent and zone settings')
            ));

        }

        $this->setButtons();
    }

    protected function enumHostsAndTemplates()
    {
        return array(
            $this->translate('Templates') => $this->db->enumHostTemplates(),
            $this->translate('Hosts')     => $this->db->enumHosts(),
        );
    }

    protected function enumServicegroups()
    {
        $db = $this->db->getDbAdapter();
        $select = $db->select()->from(
            'icinga_servicegroup',
            array(
                'name'    => 'object_name',
                'display' => 'COALESCE(display_name, object_name)'
            )
        )->where('object_type = ?', 'object')->order('display');

        return $db->fetchPairs($select);
    }
}
