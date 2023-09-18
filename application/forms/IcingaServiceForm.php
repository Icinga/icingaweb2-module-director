<?php

namespace Icinga\Module\Director\Forms;

use gipfl\Web\Widget\Hint;
use Icinga\Data\Filter\Filter;
use Icinga\Exception\IcingaException;
use Icinga\Exception\ProgrammingError;
use Icinga\Module\Director\Auth\Permission;
use Icinga\Module\Director\Data\PropertiesFilter\ArrayCustomVariablesFilter;
use Icinga\Module\Director\Exception\NestingError;
use Icinga\Module\Director\Objects\IcingaObject;
use Icinga\Module\Director\Web\Form\DirectorObjectForm;
use Icinga\Module\Director\Objects\IcingaHost;
use Icinga\Module\Director\Objects\IcingaService;
use Icinga\Module\Director\Objects\IcingaServiceSet;
use Icinga\Module\Director\Web\Table\ObjectsTableHost;
use ipl\Html\Html;
use gipfl\IcingaWeb2\Link;
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
            // Probably never reached, as providesOverrides includes this
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

    public function providesOverrides()
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
        $hasDeleteButton = false;
        $isBranch = $this->branch && $this->branch->isBranch();

        if ($this->hasBeenBlacklisted()) {
            $this->addHtml(
                Hint::warning($this->translate('This Service has been deactivated on this host')),
                ['name' => 'HINT_blacklisted']
            );
            $group = null;
            if (! $isBranch) {
                $this->addDeleteButton($this->translate('Reactivate'));
                $hasDeleteButton = true;
            }
            $this->setSubmitLabel(false);
        } else {
            $this->addOverrideHint();
            $group = $this->getDisplayGroup('custom_fields');
            if (! $group) {
                foreach ($this->getDisplayGroups() as $groupName => $eventualGroup) {
                    if (preg_match('/^custom_fields:/', $groupName)) {
                        $group = $eventualGroup;
                        break;
                    }
                }
            }
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

            if (! $isBranch) {
                $this->addDeleteButton($this->translate('Deactivate'));
                $hasDeleteButton = true;
            }
        }

        if (! $this->hasSubmitButton() && $hasDeleteButton) {
            $this->addDisplayGroup([$this->deleteButtonName], 'buttons', [
                'decorators' => [
                    'FormElements',
                    ['HtmlTag', ['tag' => 'dl']],
                    'DtDdWrapper',
                ],
                'order' => self::GROUP_ORDER_BUTTONS,
            ]);
        }
    }

    /**
     * @return IcingaHost|null
     */
    public function getHost()
    {
        return $this->host;
    }

    /**
     * Hint: could be moved elsewhere
     *
     * @param IcingaService $object
     * @return IcingaObject|IcingaService|IcingaServiceSet
     * @throws \Icinga\Exception\NotFoundError
     */
    protected static function getFirstParent(IcingaObject $object)
    {
        /** @var IcingaObject[] $objects */
        $objects = $object->imports()->getObjects();
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
            // Safety check, branches
            $hostId = $host->get('id');
            $service = $this->getServiceToBeBlacklisted();
            $serviceId = $service->get('id');
            if (! $hostId || ! $serviceId) {
                return false;
            }
            $db = $this->db->getDbAdapter();
            if ($this->providesOverrides()) {
                $this->blacklisted = 1 === (int)$db->fetchOne(
                    $db->select()->from('icinga_host_service_blacklist', 'COUNT(*)')
                        ->where('host_id = ?', $hostId)
                        ->where('service_id = ?', $serviceId)
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
                $this->translate('%s has been deactivated on %s'),
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
            return self::getFirstParent($this->object);
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
                $this->translate('%s is no longer deactivated on %s'),
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
            $objectType = $this->object->get('object_type');
        } elseif ($this->preferredObjectType) {
            $objectType = $this->preferredObjectType;
        } else {
            $objectType = 'template';
        }
        $this->addHidden('object_type', $objectType);
        $forceCommandElements = $this->hasPermission(Permission::ADMIN);

        $this->addNameElement()
             ->addHostObjectElement()
             ->addImportsElement()
             ->addChoices('service')
             ->addGroupsElement()
             ->addDisabledElement()
             ->addApplyForElement()
             ->groupMainProperties()
             ->addAssignmentElements()
             ->addCheckCommandElements($forceCommandElements)
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
        $this->addHidden('host', $this->host->getObjectName());
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

        $this->setDefaultNameFromTemplate($imports);
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
        $this->addHidden('service_set', $this->set->getObjectName());
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

        if ($this->hasPermission(Permission::ADMIN)) {
            $this->addCheckCommandElements(true)
                ->addCheckExecutionElements(true)
                ->addExtraInfoElements();
        }

        $this->setDefaultNameFromTemplate($imports);
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
            $this->addElement('select', 'host', [
                'label'       => $this->translate('Host'),
                'required'    => true,
                'multiOptions' => $this->optionalEnum($this->enumHostsAndTemplates()),
                'description' => $this->translate(
                    'Choose the host this single service should be assigned to'
                )
            ]);
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
            'order' => self::GROUP_ORDER_CLUSTERING,
            'legend' => $this->translate('Icinga Agent and zone settings')
        ));

        return $this;
    }

    protected function enumHostsAndTemplates()
    {
        if ($this->branch && $this->branch->isBranch()) {
            return $this->enumHosts();
        }

        return [
            $this->translate('Templates') => $this->enumHostTemplates(),
            $this->translate('Hosts')     => $this->enumHosts(),
        ];
    }

    protected function enumHostTemplates()
    {
        $names = array_values($this->db->enumHostTemplates());
        return array_combine($names, $names);
    }

    protected function enumHosts()
    {
        $db = $this->db->getDbAdapter();
        $table = new ObjectsTableHost($this->db);
        $table->setAuth($this->getAuth());
        if ($this->branch && $this->branch->isBranch()) {
            $table->setBranchUuid($this->branch->getUuid());
        }
        $result = [];
        foreach ($db->fetchAll($table->getQuery()->reset(\Zend_Db_Select::LIMIT_COUNT)) as $row) {
            $result[$row->object_name] = $row->object_name;
        }

        return $result;
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
        $serviceName = $this->object->getObjectName();

        $this->host->overrideServiceVars($serviceName, (object) $vars);

        if ($host->hasBeenModified()) {
            $msg = sprintf(
                empty($vars)
                ? $this->translate('All overrides have been removed from "%s"')
                : $this->translate('The given properties have been stored for "%s"'),
                $this->translate($host->getObjectName())
            );

            $this->getDbObjectStore()->store($host);
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
        if ($this->providesOverrides()) {
            $this->succeedForOverrides();
            return;
        }

        parent::onSuccess();
    }

    /**
     * @param array $imports
     */
    protected function setDefaultNameFromTemplate($imports)
    {
        if ($this->hasBeenSent()) {
            $name = $this->getSentOrObjectValue('object_name');
            if ($name === null || !strlen($name)) {
                $this->setElementValue('object_name', end($imports));
                $this->object->set('object_name', end($imports));
            }
        }
    }
}
