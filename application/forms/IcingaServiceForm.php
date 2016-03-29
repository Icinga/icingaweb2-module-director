<?php

namespace Icinga\Module\Director\Forms;

use Icinga\Module\Director\Web\Form\DirectorObjectForm;
use Icinga\Module\Director\Objects\IcingaHost;

class IcingaServiceForm extends DirectorObjectForm
{
    private $host;

    public function setup()
    {
        if (!$this->isNew() && $this->host === null) {
            $this->host = $this->object->getResolvedRelated('host');
        }

        if ($this->host === null) {
            $this->setupServiceElements();
        } else {
            $this->setupHostRelatedElements();
        }
    }

    protected function setupServiceElements()
    {
        $this->addHidden('object_type', 'template');

        $this->addNameElement()
             ->addHostObjectElement()
             ->addImportsElement()
             ->addGroupsElement()
             ->addDisabledElement()
             ->groupMainProperties()
             ->addAssignmentElements()
             ->addCheckCommandElements()
             ->addCheckExecutionElements()
             ->addAgentAndZoneElements()
             ->setButtons();
    }

    protected function addAssignmentElements()
    {
        if (!$this->object || !$this->object->isApplyRule()) {
            return $this;
        }

        $sub = new AssignListSubForm();
        $sub->setObject($this->getObject());
        $sub->setup();
        $sub->setOrder(30);

        $this->addSubForm($sub, 'assignlist');

        return $this;
    }

    protected function setupHostRelatedElements()
    {
        $this->addHidden('host_id', $this->host->id);
        $this->addHidden('object_type', 'object');
        $this->addImportsElement();
        $imports = $this->getSentOrObjectValue('imports');
        if ($this->isNew() && empty($imports)) {
            return $this->groupMainProperties();
        }

        $this->addNameElement()
             ->addDisabledElement()
             ->groupMainProperties()
             ->addCheckCommandElements()
             ->setButtons();

        if ($this->hasBeenSent()) {
            $name = $this->getValue('object_name');
            if (!strlen($name)) {
                $this->setElementValue('object_name', end($imports));
                $this->object->object_name = end($imports);
            }
        }
    }

    public function setHost(IcingaHost $host)
    {
        $this->host = $host;
        return $this;
    }

    protected function addNameElement()
    {
        $this->addElement('text', 'object_name', array(
            'label'       => $this->translate('Name'),
            'required'    => true,
            'description' => $this->translate(
                'Name for the Icinga service you are going to create'
            )
        ));

        return $this;
    }


    protected function addHostObjectElement()
    {
        if ($this->isObject()) {
            $this->addElement('select', 'host_id', array(
                'label'       => $this->translate('Host'),
                'required'    => true,
                'multiOptions' => $this->optionalEnum($this->enumHostsAndTemplates()),
                'description' => $this->translate(
                    'Choose the host this single service should be assigned to'
                )
            ));
        }

        return $this;
    }

    protected function addGroupsElement()
    {
        $groups = $this->enumServicegroups();

        if (! empty($groups)) {
            $this->addElement('extensibleSet', 'groups', array(
                'label'        => $this->translate('Groups'),
                'multiOptions' => $this->optionallyAddFromEnum($groups),
                'positional'   => false,
                'description'  => $this->translate(
                    'Service groups that should be directly assigned to this service.'
                    . ' Servicegroups can be useful for various reasons. They are'
                    . ' helpful to provided service-type specific view in Icinga Web 2,'
                    . ' either for custom dashboards or as an instrument to enforce'
                    . ' restrictions. Service groups can be directly assigned to'
                    . ' single services or to service templates.'
                )
            ));
        }

        return $this;
    }

    protected function addAgentAndZoneElements()
    {
        if (!$this->isTemplate()) {
            return $this;
        }

        $this->optionalBoolean(
            'use_agent',
            $this->translate('Run on agent'),
            $this->translate(
                'Whether the check commmand for this service should be executed'
                . ' on the Icinga agent'
            )
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

        return $this;
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
