<?php

namespace Icinga\Module\Director\Forms;

use Icinga\Exception\AuthenticationException;
use Icinga\Module\Director\Repository\IcingaTemplateRepository;
use Icinga\Module\Director\Restriction\HostgroupRestriction;
use Icinga\Module\Director\Web\Form\DirectorObjectForm;
use ipl\Html\BaseHtmlElement;
use ipl\Html\Html;
use gipfl\IcingaWeb2\Link;

class IcingaHostForm extends DirectorObjectForm
{
    public function setup()
    {
        $this->addObjectTypeElement();
        if (! $this->hasObjectType()) {
            $this->groupMainProperties();
            return;
        }

        $simpleImports = $this->isNew() && ! $this->isTemplate();
        if ($simpleImports) {
            if (!$this->addSingleImportElement(true)) {
                $this->setSubmitLabel(false);
                return;
            }

            if (! ($imports = $this->getSentOrObjectValue('imports'))) {
                $this->setSubmitLabel($this->translate('Next'));
                $this->groupMainProperties();
                return;
            }
        }

        $nameLabel = $this->isTemplate()
            ? $this->translate('Name')
            : $this->translate('Hostname');

        $this->addElement('text', 'object_name', array(
            'label'       => $nameLabel,
            'required'    => true,
            'spellcheck'  => 'false',
            'description' => $this->translate(
                'Icinga object name for this host. This is usually a fully qualified host name'
                . ' but it could basically be any kind of string. To make things easier for your'
                . ' users we strongly suggest to use meaningful names for templates. E.g. "generic-host"'
                . ' is ugly, "Standard Linux Server" is easier to understand'
            )
        ));

        if (! $simpleImports) {
            $this->addImportsElement();
        }

        $this->addChoices('host')
             ->addDisplayNameElement()
             ->addAddressElements()
             ->addGroupsElement()
             ->addDisabledElement()
             ->groupMainProperties($simpleImports)
             ->addCheckCommandElements()
             ->addCheckExecutionElements()
             ->addExtraInfoElements()
             ->addClusteringElements()
             ->setButtons();
    }

    /**
     * @return $this
     */
    protected function addClusteringElements()
    {
        $this->addZoneElement();
        $this->addBoolean('has_agent', [
            'label'       => $this->translate('Icinga2 Agent'),
            'description' => $this->translate(
                'Whether this host has the Icinga 2 Agent installed'
            ),
            'class' => 'autosubmit',
        ]);

        if ($this->getSentOrResolvedObjectValue('has_agent') === 'y') {
            $this->addBoolean('master_should_connect', [
                'label'       => $this->translate('Establish connection'),
                'description' => $this->translate(
                    'Whether the parent (master) node should actively try to connect to this agent'
                ),
                'required'    => true
            ]);
            $this->addBoolean('accept_config', [
                'label'       => $this->translate('Accepts config'),
                'description' => $this->translate('Whether the agent is configured to accept config'),
                'required'    => true
            ]);

            $this->addHidden('command_endpoint_id', null);
            $this->setSentValue('command_endpoint_id', null);
        } else {
            if ($this->isTemplate()) {
                $this->addElement('select', 'command_endpoint_id', [
                    'label' => $this->translate('Command endpoint'),
                    'description' => $this->translate(
                        'Setting a command endpoint allows you to force host checks'
                        . ' to be executed by a specific endpoint. Please carefully'
                        . ' study the related Icinga documentation before using this'
                        . ' feature'
                    ),
                    'multiOptions' => $this->optionalEnum($this->enumEndpoints())
                ]);
            }

            foreach (['master_should_connect', 'accept_config'] as $key) {
                $this->addHidden($key, null);
                $this->setSentValue($key, null);
            }
        }

        $elements = [
            'zone_id',
            'has_agent',
            'master_should_connect',
            'accept_config',
            'command_endpoint_id',
            'api_key',
        ];
        $this->addDisplayGroup($elements, 'clustering', [
            'decorators' => [
                'FormElements',
                ['HtmlTag', ['tag' => 'dl']],
                'Fieldset',
            ],
            'order'  => self::GROUP_ORDER_CLUSTERING,
            'legend' => $this->translate('Icinga Agent and zone settings')
        ]);

        return $this;
    }

    /**
     * @param bool $required
     * @return bool
     */
    protected function addSingleImportElement($required = null)
    {
        $enum = $this->enumHostTemplates();
        if (empty($enum)) {
            if ($required) {
                if ($this->hasBeenSent()) {
                    $this->addError($this->translate('No Host template has been chosen'));
                } else {
                    if ($this->hasPermission('director/admin')) {
                        $html = sprintf(
                            $this->translate('Please define a %s first'),
                            Link::create(
                                $this->translate('Host Template'),
                                'director/host/add',
                                ['type' => 'template']
                            )
                        );
                    } else {
                        $html = $this->translate('No Host Template has been provided yet');
                    }

                    $this->addHtml('<p class="warning">' . $html . '</p>');
                }
            }

            return false;
        }

        $this->addElement('select', 'imports', [
            'label'        => $this->translate('Host Template'),
            'description'  => $this->translate(
                'Choose a Host Template'
            ),
            'required'     => true,
            'multiOptions' => $this->optionalEnum($enum),
            'class'        => 'autosubmit'
        ]);

        return true;
    }

    protected function enumHostTemplates()
    {
        $tpl = IcingaTemplateRepository::instanceByType('host', $this->getDb())
            ->listAllowedTemplateNames();
        return array_combine($tpl, $tpl);
    }

    /**
     * @return $this
     */
    protected function addGroupsElement()
    {
        if ($this->hasHostGroupRestriction()
            && ! $this->getAuth()->hasPermission('director/groups-for-restricted-hosts')
        ) {
            return $this;
        }

        $this->addElement('extensibleSet', 'groups', array(
            'label'        => $this->translate('Groups'),
            'suggest'      => 'hostgroupnames',
            'description'  => $this->translate(
                'Hostgroups that should be directly assigned to this node. Hostgroups can be useful'
                . ' for various reasons. You might assign service checks based on assigned hostgroup.'
                . ' They are also often used as an instrument to enforce restricted views in Icinga Web 2.'
                . ' Hostgroups can be directly assigned to single hosts or to host templates. You might'
                . ' also want to consider assigning hostgroups using apply rules'
            )
        ));

        $applied = $this->getAppliedGroups();
        if (! empty($applied)) {
            $this->addElement('simpleNote', 'applied_groups', [
                'label'  => $this->translate('Applied groups'),
                'value'  => $this->createHostgroupLinks($applied),
                'ignore' => true,
            ]);
        }

        $inherited = $this->getInheritedGroups();
        if (! empty($inherited)) {
            /** @var BaseHtmlElement $links */
            $links = $this->createHostgroupLinks($inherited);
            if (count($this->object()->getGroups())) {
                $links->addAttributes(['class' => 'strike-links']);
                /** @var BaseHtmlElement $link */
                foreach ($links->getContent() as $link) {
                    if ($link instanceof BaseHtmlElement) {
                        $link->addAttributes([
                            'title' => $this->translate(
                                'Group has been inherited, but will be overridden'
                                . ' by locally assigned group(s)'
                            )
                        ]);
                    }
                }
            }
            $this->addElement('simpleNote', 'inherited_groups', [
                'label'  => $this->translate('Inherited groups'),
                'value'  => $links,
                'ignore' => true,
            ]);
        }

        return $this;
    }

    protected function strikeGroupLinks(BaseHtmlElement $links)
    {
        /** @var BaseHtmlElement $link */
        foreach ($links->getContent() as $link) {
            $link->getAttributes()->add('style', 'text-decoration: strike');
        }
        $links->add('aha');
    }

    protected function getInheritedGroups()
    {
        if ($this->hasObject()) {
            return $this->object->listInheritedGroupNames();
        } else {
            return [];
        }
    }

    protected function createHostgroupLinks($groups)
    {
        $links = [];
        foreach ($groups as $name) {
            if (! empty($links)) {
                $links[] = ', ';
            }
            $links[] = Link::create(
                $name,
                'director/hostgroup',
                ['name' => $name],
                ['data-base-target' => '_next']
            );
        }

        return Html::tag('span', [
            'style' => 'line-height: 2.5em; padding-left: 0.5em'
        ], $links);
    }

    protected function getAppliedGroups()
    {
        if ($this->isNew()) {
            return [];
        }

        return $this->object()->getAppliedGroups();
    }

    protected function hasHostGroupRestriction()
    {
        return $this->getAuth()->getRestrictions('director/filter/hostgroups');
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
            'spellcheck'  => 'false',
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
        $select = $db->select()->from('icinga_endpoint', [
            'id',
            'object_name'
        ])->where(
            'object_type IN (?)',
            ['object', 'external_object']
        )->order('object_name');

        return $db->fetchPairs($select);
    }

    public function onSuccess()
    {
        if ($this->hasHostGroupRestriction()) {
            $restriction = new HostgroupRestriction($this->getDb(), $this->getAuth());
            if (! $restriction->allowsHost($this->object())) {
                throw new AuthenticationException($this->translate(
                    'Unable to store a host with the given properties because of insufficient permissions'
                ));
            }
        }

        parent::onSuccess();
    }
}
