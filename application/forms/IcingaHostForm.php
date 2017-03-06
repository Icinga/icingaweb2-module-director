<?php

namespace Icinga\Module\Director\Forms;

use Icinga\Module\Director\Restriction\BetaHostgroupRestriction;
use Icinga\Module\Director\Web\Form\DirectorObjectForm;

class IcingaHostForm extends DirectorObjectForm
{
    public function setup()
    {
        $this->addObjectTypeElement();
        if (! $this->hasObjectType()) {
            $this->groupMainProperties();
            return;
        }

        $this->addElement('text', 'object_name', array(
            'label'       => $this->translate('Hostname'),
            'required'    => true,
            'description' => $this->translate(
                'Icinga object name for this host. This is usually a fully qualified host name'
                . ' but it could basically be any kind of string. To make things easier for your'
                . ' users we strongly suggest to use meaningful names for templates. E.g. "generic-host"'
                . ' is ugly, "Standard Linux Server" is easier to understand'
            )
        ));

        if ($this->isNew() && $this->isObject() && $this->allowsExperimental()) {
            $this->addBoolean('create_live', array(
                'label'  => $this->translate('Create immediately'),
                'ignore' => true,
            ), 'n');
        }

        $this->addGroupsElement()
             ->addImportsElement()
             ->addDisplayNameElement()
             ->addAddressElements()
             ->addDisabledElement()
             ->groupMainProperties()
             ->addClusteringElements();

        $this->addCheckCommandElements()
             ->addCheckExecutionElements()
             ->addExtraInfoElements()
             ->setButtons();
    }

    /**
     * @return $this
     */
    protected function addClusteringElements()
    {
        $this->addZoneElement();

        $this->addBoolean('has_agent', array(
            'label'       => $this->translate('Icinga2 Agent'),
            'description' => $this->translate(
                'Whether this host has the Icinga 2 Agent installed'
            ),
            'class'       => 'autosubmit',
        ));

        if ($this->getSentOrResolvedObjectValue('has_agent') === 'y') {
            $this->addBoolean('master_should_connect', array(
                'label'       => $this->translate('Establish connection'),
                'description' => $this->translate(
                    'Whether the parent (master) node should actively try to connect to this agent'
                ),
                'required'    => true
            ));
            $this->addBoolean('accept_config', array(
                'label'       => $this->translate('Accepts config'),
                'description' => $this->translate('Whether the agent is configured to accept config'),
                'required'    => true
            ));

            $this->addHidden('command_endpoint_id', null);
            $this->setSentValue('command_endpoint_id', null);
        } else {
            if ($this->isTemplate()) {
                $this->addElement('select', 'command_endpoint_id', array(
                    'label' => $this->translate('Command endpoint'),
                    'description' => $this->translate(
                        'Setting a command endpoint allows you to force host checks'
                        . ' to be executed by a specific endpoint. Please carefully'
                        . ' study the related Icinga documentation before using this'
                        . ' feature'
                    ),
                    'multiOptions' => $this->optionalEnum($this->enumEndpoints())
                ));
            }

            foreach (array('master_should_connect', 'accept_config') as $key) {
                $this->addHidden($key, null);
                $this->setSentValue($key, null);
            }
        }

        $elements = array(
            'zone_id',
            'has_agent',
            'master_should_connect',
            'accept_config',
            'command_endpoint_id',
        );
        $this->addDisplayGroup($elements, 'clustering', array(
            'decorators' => array(
                'FormElements',
                array('HtmlTag', array('tag' => 'dl')),
                'Fieldset',
            ),
            'order' => 80,
            'legend' => $this->translate('Icinga Agent and zone settings')
        ));

        return $this;
    }

    protected function beforeSuccessfulRedirect()
    {
        if ($this->allowsExperimental() && $this->getSentValue('create_live') === 'y') {
            $host = $this->getObject();
            if ($this->api()->createObjectAtRuntime($host)) {
                $this->api()->checkHostNow($host->object_name);
            }
        }
    }

    /**
     * @return $this
     */
    protected function addGroupsElement()
    {
        if ($this->hasHostGroupRestriction()) {
            return $this;
        }

        $groups = $this->enumHostgroups();
        if (empty($groups)) {
            return $this;
        }

        $this->addElement('extensibleSet', 'groups', array(
            'label'        => $this->translate('Groups'),
            'multiOptions' => $this->optionallyAddFromEnum($groups),
            'positional'   => false,
            'description'  => $this->translate(
                'Hostgroups that should be directly assigned to this node. Hostgroups can be useful'
                . ' for various reasons. You might assign service checks based on assigned hostgroup.'
                . ' They are also often used as an instrument to enforce restricted views in Icinga Web 2.'
                . ' Hostgroups can be directly assigned to single hosts or to host templates. You might'
                . ' also want to consider assigning hostgroups using apply rules'
            )
        ));

        return $this;
    }

    protected function hasHostGroupRestriction()
    {
        foreach ($this->getObjectRestrictions() as $restriction) {
            if ($restriction instanceof BetaHostgroupRestriction) {
                if ($restriction->isRestricted()) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @return $this
     */
    protected function addAddressElements()
    {
        if ($this->isTemplate()) {
            return $this;
        }

        $this->addElement('text', 'address', array(
            'label' => $this->translate('Host address'),
            'description' => $this->translate(
                'Host address. Usually an IPv4 address, but may be any kind of address'
                . ' your check plugin is able to deal with'
            )
        ));

        $this->addElement('text', 'address6', array(
            'label' => $this->translate('IPv6 address'),
            'description' => $this->translate('Usually your hosts main IPv6 address')
        ));

        return $this;
    }

    /**
     * @return $this
     */
    protected function addDisplayNameElement()
    {
        if ($this->isTemplate()) {
            return $this;
        }

        $this->addElement('text', 'display_name', array(
            'label' => $this->translate('Display name'),
            'description' => $this->translate(
                'Alternative name for this host. Might be a host alias or and kind'
                . ' of string helping your users to identify this host'
            )
        ));

        return $this;
    }

    protected function enumEndpoints()
    {
        $db = $this->db->getDbAdapter();
        $select = $db->select()->from(
            'icinga_endpoint',
            array(
                'id',
                'object_name'
            )
        )->where(
            'object_type IN (?)',
            array('object', 'external_object')
        )->order('object_name');

        return $db->fetchPairs($select);
    }

    protected function enumHostgroups()
    {
        $db = $this->db->getDbAdapter();
        $select = $db->select()->from(
            'icinga_hostgroup',
            array(
                'name'    => 'object_name',
                'display' => 'COALESCE(display_name, object_name)'
            )
        )->where(
            'object_type IN (?)',
            array('object', 'external_object')
        )->order('display');

        return $db->fetchPairs($select);
    }
}
