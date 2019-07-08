<?php

namespace Icinga\Module\Director\Forms;

use Icinga\Data\Filter\Filter;
use Icinga\Exception\IcingaException;
use Icinga\Exception\ProgrammingError;
use Icinga\Module\Director\Data\PropertiesFilter\ArrayCustomVariablesFilter;
use Icinga\Module\Director\Exception\NestingError;
use Icinga\Module\Director\Web\Form\DirectorObjectForm;
use Icinga\Module\Director\Objects\IcingaHost;
use Icinga\Module\Director\Objects\IcingaService;
use Icinga\Module\Director\Objects\IcingaServiceSet;
use dipl\Html\Html;
use dipl\Html\Link;
use RuntimeException;

class IcingaServiceForm extends DirectorObjectForm
{
    /** @var IcingaHost */
    private $host;

    /** @var IcingaServiceSet */
    private $set;

    private $apply;

    /** @var IcingaService */
    protected $object;

    /** @var IcingaService */
    private $applyGenerated;

    private $inheritedFrom;

    /** @var bool|null */
    private $blacklisted;

    public function setApplyGenerated(IcingaService $applyGenerated)
    {
        $this->applyGenerated = $applyGenerated;

        return $this;
    }

    public function setInheritedFrom($hostname)
    {
        $this->inheritedFrom = $hostname;

        return $this;
    }

    /**
     * @throws IcingaException
     * @throws ProgrammingError
     * @throws \Zend_Form_Exception
     */
    public function setup()
    {
        if (!$this->isNew() || $this->providesOverrides()) {
            $this->tryToFetchHost();
        }

        if ($this->providesOverrides()) {
            return;
        }

        if ($this->host && $this->set) {
            $this->setupOnHostForSet();

            return;
        }

        if ($this->set !== null) {
            $this->setupSetRelatedElements();
        } elseif ($this->host === null) {
            $this->setupServiceElements();
        } else {
            $this->setupHostRelatedElements();
        }
    }

    protected function tryToFetchHost()
    {
        try {
            if ($this->host === null) {
                $this->host = $this->object->getResolvedRelated('host');
            }
        } catch (NestingError $nestingError) {
            // ignore for the form to load
        }
    }

    protected function providesOverrides()
    {
        return $this->applyGenerated
            || $this->inheritedFrom
            || ($this->host && $this->set)
            || ($this->object && $this->object->usesVarOverrides());
    }

    /**
     * @throws IcingaException
     * @throws ProgrammingError
     * @throws \Zend_Form_Exception
     */
    protected function addFields()
    {
        if ($this->providesOverrides() && $this->hasBeenBlacklisted()) {
            $this->onAddedFields();

            return;
        } else {
            parent::addFields();
        }
    }

    /**
     * @throws IcingaException
     * @throws ProgrammingError
     * @throws \Zend_Form_Exception
     */
    protected function onAddedFields()
    {
        if (! $this->providesOverrides()) {
            return;
        }

        if ($this->hasBeenBlacklisted()) {
            $this->addHtml(
                Html::tag(
                    'p',
                    ['class' => 'warning'],
                    $this->translate('This Service has been blacklisted on this host')
                ),
                ['name' => 'HINT_blacklisted']
            );
            $group = null;
            $this->addDeleteButton($this->translate('Restore'));
            $this->setSubmitLabel(false);
        } else {
            $this->addOverrideHint();
            $group = $this->getDisplayGroup('custom_fields');
            if ($group) {
                $elements = $group->getElements();
                $group->setElements([$this->getElement('inheritance_hint')]);
                $group->addElements($elements);
                $this->setSubmitLabel($this->translate('Override vars'));
            } else {
                $this->addElementsToGroup(
                    ['inheritance_hint'],
                    'custom_fields',
                    20,
                    $this->translate('Hints regarding this service')
                );

                $this->setSubmitLabel(false);
            }

            $this->addDeleteButton($this->translate('Blacklist'));
        }

        if (! $this->hasSubmitButton()) {
            $this->addDisplayGroup([$this->deleteButtonName], 'buttons', [
                'decorators' => [
                    'FormElements',
                    ['HtmlTag', ['tag' => 'dl']],
                    'DtDdWrapper',
                ],
                'order' => 1000,
            ]);
        }
    }

    /**
     * @param IcingaService $service
     * @return IcingaService
     * @throws \Icinga\Exception\NotFoundError
     */
    protected function getFirstParent(IcingaService $service)
    {
        $objects = $service->imports()->getObjects();
        if (empty($objects)) {
            throw new RuntimeException('Something went wrong, got no parent');
        }
        reset($objects);

        return current($objects);
    }

    /**
     * @return bool
     * @throws \Icinga\Exception\NotFoundError
     */
    protected function hasBeenBlacklisted()
    {
        if (! $this->providesOverrides() || $this->object === null) {
            return false;
        }

        if ($this->blacklisted === null) {
            $host = $this->host;
            $service = $this->getServiceToBeBlacklisted();
            $db = $this->db->getDbAdapter();
            if ($this->providesOverrides()) {
                $this->blacklisted = 1 === (int)$db->fetchOne(
                    $db->select()->from('icinga_host_service_blacklist', 'COUNT(*)')
                        ->where('host_id = ?', $host->get('id'))
                        ->where('service_id = ?', $service->get('id'))
                );
            } else {
                $this->blacklisted = false;
            }
        }

        return $this->blacklisted;
    }

    /**
     * @param $object
     * @throws IcingaException
     * @throws ProgrammingError
     * @throws \Zend_Db_Adapter_Exception
     */
    protected function deleteObject($object)
    {
        /** @var IcingaService $object */
        if ($this->providesOverrides()) {
            if ($this->hasBeenBlacklisted()) {
                $this->removeFromBlacklist();
            } else {
                $this->blacklist();
            }
        } else {
            parent::deleteObject($object);
        }
    }

    /**
     * @throws IcingaException
     * @throws \Zend_Db_Adapter_Exception
     */
    protected function blacklist()
    {
        $host = $this->host;
        $service = $this->getServiceToBeBlacklisted();

        $db = $this->db->getDbAdapter();
        $host->unsetOverriddenServiceVars($this->object->getObjectName())->store();

        if ($db->insert('icinga_host_service_blacklist', [
            'host_id'    => $host->get('id'),
            'service_id' => $service->get('id')
        ])) {
            $msg = sprintf(
                $this->translate('%s has been blacklisted on %s'),
                $service->getObjectName(),
                $host->getObjectName()
            );
            $this->redirectOnSuccess($msg);
        }
    }

    /**
     * @return IcingaService
     * @throws \Icinga\Exception\NotFoundError
     */
    protected function getServiceToBeBlacklisted()
    {
        if ($this->set) {
            return $this->object;
        } else {
            return $this->getFirstParent($this->object);
        }
    }

    /**
     * @throws \Icinga\Exception\NotFoundError
     */
    protected function removeFromBlacklist()
    {
        $host = $this->host;
        $service = $this->getServiceToBeBlacklisted();

        $db = $this->db->getDbAdapter();
        $where = implode(' AND ', [
            $db->quoteInto('host_id = ?', $host->get('id')),
            $db->quoteInto('service_id = ?', $service->get('id')),
        ]);
        if ($db->delete('icinga_host_service_blacklist', $where)) {
            $msg = sprintf(
                $this->translate('%s is no longer blacklisted on %s'),
                $service->getObjectName(),
                $host->getObjectName()
            );
            $this->redirectOnSuccess($msg);
        }
    }

    /**
     * @param IcingaService $service
     * @return $this
     */
    public function createApplyRuleFor(IcingaService $service)
    {
        $this->apply = $service;
        $object = $this->object();
        $object->set('imports', $service->getObjectName());
        $object->set('object_type', 'apply');
        $object->set('object_name', $service->getObjectName());

        return $this;
    }

    /**
     * @throws \Zend_Form_Exception
     */
    protected function setupServiceElements()
    {
        if ($this->object) {
            $this->addHidden('object_type', $this->object->object_type);
        } elseif ($this->preferredObjectType) {
            $this->addHidden('object_type', $this->preferredObjectType);
        } else {
            $this->addHidden('object_type', 'template');
        }

        $this->addNameElement()
             ->addHostObjectElement()
             ->addImportsElement()
             ->addChoices('service')
             ->addGroupsElement()
             ->addDisabledElement()
             ->addApplyForElement()
             ->groupMainProperties()
             ->addAssignmentElements()
             ->addCheckCommandElements()
             ->addCheckExecutionElements()
             ->addExtraInfoElements()
             ->addAgentAndZoneElements()
             ->setButtons();
    }

    /**
     * @throws IcingaException
     * @throws ProgrammingError
     */
    protected function addOverrideHint()
    {
        if ($this->object && $this->object->usesVarOverrides()) {
            $hint = $this->translate(
                'This service has been generated in an automated way, but still'
                . ' allows you to override the following properties in a safe way.'
            );
        } elseif ($apply = $this->applyGenerated) {
            $hint = Html::sprintf(
                $this->translate(
                    'This service has been generated using the %s apply rule, assigned where %s'
                ),
                Link::create(
                    $apply->getObjectName(),
                    'director/service',
                    ['id' => $apply->get('id')],
                    ['data-base-target' => '_next']
                ),
                (string) Filter::fromQueryString($apply->assign_filter)
            );
        } elseif ($this->host && $this->set) {
            $hint = Html::sprintf(
                $this->translate(
                    'This service belongs to the %s Service Set. Still, you might want'
                    . ' to override the following properties for this host only.'
                ),
                Link::create(
                    $this->set->getObjectName(),
                    'director/serviceset',
                    ['id' => $this->set->get('id')],
                    ['data-base-target' => '_next']
                )
            );
        } elseif ($this->inheritedFrom) {
            $msg = $this->translate(
                'This service has been inherited from %s. Still, you might want'
                . ' to change the following properties for this host only.'
            );

            $name = $this->inheritedFrom;
            $link = Link::create(
                $name,
                'director/service',
                [
                    'host' => $name,
                    'name' => $this->object->getObjectName(),
                ],
                ['data-base-target' => '_next']
            );

            $hint = Html::sprintf($msg, $link);
        } else {
            throw new ProgrammingError('Got no override hint for your situation');
        }

        $this->setSubmitLabel($this->translate('Override vars'));

        $this->addHtmlHint($hint, ['name' => 'inheritance_hint']);
    }

    /**
     * @throws IcingaException
     * @throws ProgrammingError
     */
    protected function setupOnHostForSet()
    {
        $msg = $this->translate(
            'This service belongs to the service set "%s". Still, you might want'
            . ' to change the following properties for this host only.'
        );

        $name = $this->set->getObjectName();
        $link = Link::create(
            $name,
            'director/serviceset',
            ['name' => $name],
            ['data-base-target' => '_next']
        );

        $this->addHtmlHint(
            Html::sprintf($msg, $link),
            ['name' => 'inheritance_hint']
        );

        $this->addElementsToGroup(
            ['inheritance_hint'],
            'custom_fields',
            50,
            $this->translate('Custom properties')
        );

        $this->setSubmitLabel($this->translate('Override vars'));
    }

    protected function addAssignmentElements()
    {
        $this->addAssignFilter([
            'suggestionContext' => 'HostFilterColumns',
            'required' => true,
            'description' => $this->translate(
                'This allows you to configure an assignment filter. Please feel'
                . ' free to combine as many nested operators as you want. The'
                . ' "contains" operator is valid for arrays only. Please use'
                . ' wildcards and the = (equals) operator when searching for'
                . ' partial string matches, like in *.example.com'
            )
        ]);

        return $this;
    }

    /**
     * @throws \Zend_Form_Exception
     */
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
            $this->groupMainProperties();
            return;
        }

        $this->addNameElement()
             ->addChoices('service')
             ->addDisabledElement()
             ->addGroupsElement()
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

    /**
     * @param IcingaHost $host
     * @return $this
     */
    public function setHost(IcingaHost $host)
    {
        $this->host = $host;
        return $this;
    }

    /**
     * @throws \Zend_Form_Exception
     */
    protected function setupSetRelatedElements()
    {
        $this->addHidden('service_set_id', $this->set->id);
        $this->addHidden('object_type', 'apply');
        $this->addImportsElement();
        $this->setButtons();
        $imports = $this->getSentOrObjectValue('imports');

        if ($this->hasBeenSent()) {
            $imports = $this->getElement('imports')->setValue($imports)->getValue();
        }

        if ($this->isNew() && empty($imports)) {
            $this->groupMainProperties();
            return;
        }

        $this->addNameElement()
             ->addDisabledElement()
             ->addGroupsElement()
             ->groupMainProperties();

        if ($this->hasPermission('director/admin')) {
            $this->addCheckCommandElements(true)
                ->addCheckExecutionElements(true)
                ->addExtraInfoElements();
        }

        if ($this->hasBeenSent()) {
            $name = $this->getSentOrObjectValue('object_name');
            if (!strlen($name)) {
                $this->setElementValue('object_name', end($imports));
                $this->object->object_name = end($imports);
            }
        }
    }

    public function setServiceSet(IcingaServiceSet $set)
    {
        $this->set = $set;
        return $this;
    }

    /**
     * @return $this
     * @throws \Zend_Form_Exception
     */
    protected function addNameElement()
    {
        $this->addElement('text', 'object_name', array(
            'label'       => $this->translate('Name'),
            'required'    => !$this->object()->isApplyRule(),
            'description' => $this->translate(
                'Name for the Icinga service you are going to create'
            )
        ));

        if ($this->object()->isApplyRule()) {
            $this->eventuallyAddNameRestriction('director/service/apply/filter-by-name');
        }

        return $this;
    }

    /**
     * @return $this
     * @throws \Zend_Form_Exception
     */
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

    /**
     * @return $this
     * @throws \Zend_Form_Exception
     */
    protected function addApplyForElement()
    {
        if ($this->object->isApplyRule()) {
            $hostProperties = IcingaHost::enumProperties(
                $this->object->getConnection(),
                'host.',
                new ArrayCustomVariablesFilter()
            );

            $this->addElement('select', 'apply_for', array(
                'label' => $this->translate('Apply For'),
                'class' => 'assign-property autosubmit',
                'multiOptions' => $this->optionalEnum($hostProperties, $this->translate('None')),
                'description' => $this->translate(
                    'Evaluates the apply for rule for ' .
                    'all objects with the custom attribute specified. ' .
                    'E.g selecting "host.vars.custom_attr" will generate "for (config in ' .
                    'host.vars.array_var)" where "config" will be accessible through "$config$". ' .
                    'NOTE: only custom variables of type "Array" are eligible.'
                )
            ));
        }

        return $this;
    }

    /**
     * @return $this
     * @throws \Zend_Form_Exception
     */
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

    /**
     * @return $this
     * @throws \Zend_Form_Exception
     */
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

    /**
     * @throws IcingaException
     * @throws ProgrammingError
     */
    protected function succeedForOverrides()
    {
        $vars = array();
        foreach ($this->object->vars() as $key => $var) {
            $vars[$key] = $var->getValue();
        }

        $host = $this->host;
        $serviceName = $this->object->getObjectName();

        $this->host->overrideServiceVars($serviceName, (object) $vars);

        if ($host->hasBeenModified()) {
            $msg = sprintf(
                empty($vars)
                ? $this->translate('All overrides have been removed from "%s"')
                : $this->translate('The given properties have been stored for "%s"'),
                $this->translate($host->getObjectName())
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

    /**
     * @throws IcingaException
     * @throws ProgrammingError
     */
    public function onSuccess()
    {
        if ($this->providesOverrides()) {
            return $this->succeedForOverrides();
        }

        return parent::onSuccess();
    }
}
