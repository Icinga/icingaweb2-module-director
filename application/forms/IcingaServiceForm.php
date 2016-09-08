<?php

namespace Icinga\Module\Director\Forms;

use Icinga\Module\Director\Web\Form\DirectorObjectForm;
use Icinga\Module\Director\Objects\IcingaHost;
use Icinga\Module\Director\Objects\IcingaService;

class IcingaServiceForm extends DirectorObjectForm
{
    private $host;

    private $apply;

    private $hostGenerated = false;

    private $inheritedFrom;

    public function setHostGenerated($hostGenerated = true)
    {
        $this->hostGenerated = $hostGenerated;
        return $this;
    }

    public function setInheritedFrom($hostname)
    {
        $this->inheritedFrom = $hostname;
        return $this;
    }

    public function setup()
    {
        if ($this->object->usesVarOverrides()) {
            return $this->setupForVarOverrides();
        }

        if ($this->hostGenerated) {
            return $this->setupHostGenerated();
        }

        if ($this->inheritedFrom) {
            return $this->setupInherited();
        }

        if (!$this->isNew() && $this->host === null) {
            $this->host = $this->object->getResolvedRelated('host');
        }

        if ($this->host === null) {
            $this->setupServiceElements();
        } else {
            $this->setupHostRelatedElements();
        }
    }

    public function createApplyRuleFor(IcingaService $service)
    {
        $this->apply = $service;
        $object = $this->object();
        $object->imports = $service->object_name;
        $object->object_type = 'apply';
        $object->object_name = $service->object_name;
        return $this;
    }

    protected function setupServiceElements()
    {
        if ($this->object) {
            $this->addHidden('object_type', $this->object->object_type);
        } else {
            $this->addHidden('object_type', 'template');
        }

        $this->addNameElement()
             ->addHostObjectElement()
             ->addImportsElement()
             ->addGroupsElement()
             ->addDisabledElement()
             ->groupMainProperties()
             ->addAssignmentElements()
             ->addCheckCommandElements()
             ->addCheckExecutionElements()
             ->addExtraInfoElements()
             ->addAgentAndZoneElements()
             ->setButtons();
    }

    protected function setupForVarOverrides()
    {
        $msg = $this->translate(
            'This service has been generated in an automated way, but still'
            . ' allows you to override the following properties in a safe way.'
        );

        $this->addHtmlHint($msg);

        $this->setSubmitLabel(
            $this->translate('Override vars')
        );
    }

    protected function setupHostGenerated()
    {
        $msg = $this->translate(
            'This service has been generated from host properties.'
        );

        $this->addHtmlHint($msg);

        $this->setSubmitLabel(
            $this->translate('Override vars')
        );
    }

    protected function setupInherited()
    {
        $view = $this->getView();
        $msg = $view->escape($this->translate(
            'This service has been inherited from %s. Still, you might want'
            . ' to change the following properties for this host only.'
        ));

        $name = $this->inheritedFrom;
        $link = $view->qlink(
            $name,
            'director/service',
            array(
                'host' => $name,
                'name' => $this->object->object_name,
            ),
            array('data-base-target' => '_next')
        );

        $this->addHtmlHint(sprintf($msg, $link));
        $this->setSubmitLabel(
            $this->translate('Override vars')
        );
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

        if ($this->hasBeenSent()) {
            $imports = $this->getElement('imports')->setValue($imports)->getValue();
        }

        if ($this->isNew() && empty($imports)) {
            return $this->groupMainProperties();
        }

        $this->addNameElement()
             ->addDisabledElement()
             ->groupMainProperties()
             ->addCheckCommandElements()
             ->addExtraInfoElements()
             ->setButtons();

        if ($this->hasBeenSent()) {
            $name = $this->getSentOrObjectValue('object_name');
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

    protected function succeedForOverrides()
    {

        $vars = array();
        foreach ($this->object->vars() as $key => $var) {
            $vars[$key] = $var->getValue();
        }

        $host = $this->host;
        $serviceName = $this->object->object_name;

        $this->host->overrideServiceVars($serviceName, (object) $vars);

        if ($host->hasBeenModified()) {
            $msg = sprintf(
                empty($vars)
                ? $this->translate('All overrides have been removed from "%s"')
                : $this->translate('The given properties have been stored for "%s"'),
                $this->translate($host->object_name)
            );

            $host->store();
        } else {
            if ($this->isApiRequest()) {
                $this->setHttpResponseCode(304);
            }

            $msg = $this->translate('No action taken, object has not been modified');
        }

        $this->redirectOnSuccess($msg);
    }

    public function onSuccess()
    {
        if ($this->hostGenerated || $this->inheritedFrom || $this->object->usesVarOverrides()) {
            return $this->succeedForOverrides();
        }

        return parent::onSuccess();
    }
}
