<?php

namespace Icinga\Module\Director\Objects;

use Icinga\Exception\ConfigurationError;
use Icinga\Module\Director\DirectorObject\Automation\ExportInterface;
use Icinga\Module\Director\IcingaConfig\IcingaConfigHelper as c;
use Icinga\Exception\NotFoundError;
use Icinga\Data\Filter\Filter;

class IcingaDependency extends IcingaObject implements ExportInterface
{
    protected $table = 'icinga_dependency';

    protected $defaultProperties = [
        'id'                     => null,
        'uuid'                   => null,
        'object_name'            => null,
        'object_type'            => null,
        'disabled'               => 'n',
        'apply_to'               => null,
        'parent_host_id'         => null,
        'parent_host_var'        => null,
        'parent_service_id'      => null,
        'child_host_id'          => null,
        'child_service_id'       => null,
        'disable_checks'         => null,
        'disable_notifications'  => null,
        'ignore_soft_states'     => null,
        'period_id'              => null,
        'zone_id'                => null,
        'assign_filter'          => null,
        'parent_service_by_name' => null,
    ];

    protected $uuidColumn = 'uuid';

    protected $supportsCustomVars = false;

    protected $supportsImports = true;

    protected $supportsApplyRules = true;

    /**
     * @internal
     * @var bool
     */
    protected $renderApplyForArray = false;

    protected $relatedSets = [
        'states' => 'StateFilterSet',
    ];

    protected $relations = [
        'zone'           => 'IcingaZone',
        'parent_host'    => 'IcingaHost',
        'parent_service' => 'IcingaService',
        'child_host'     => 'IcingaHost',
        'child_service'  => 'IcingaService',
        'period'         => 'IcingaTimePeriod',
    ];

    protected $booleans = [
        'disable_checks'        => 'disable_checks',
        'disable_notifications' => 'disable_notifications',
        'ignore_soft_states'    => 'ignore_soft_states'
    ];

    protected $propertiesNotForRendering = [
        'id',
        'object_name',
        'object_type',
        'apply_to',
    ];

    public function getUniqueIdentifier()
    {
        return $this->getObjectName();
    }

    public function parentHostIsVar()
    {
        return $this->get('parent_host_var') !== null;
    }

    /**
     * @return string
     * @throws ConfigurationError
     */
    protected function renderObjectHeader()
    {
        if ($this->isApplyRule()) {
            if (($to = $this->get('apply_to')) === null) {
                throw new ConfigurationError(
                    'Applied dependency "%s" has no valid object type',
                    $this->getObjectName()
                );
            }

            if ($this->renderApplyForArray) {
                return $this->renderArrayObjectHeader($to);
            }

            return $this->renderSingleObjectHeader($to);
        }

        return parent::renderObjectHeader();
    }

    protected function renderSingleObjectHeader($to)
    {
        return sprintf(
            "%s %s %s to %s {\n",
            $this->getObjectTypeName(),
            $this->getType(),
            c::renderString($this->getObjectName()),
            ucfirst($to)
        );
    }

    protected function renderArrayObjectHeader($to)
    {
        return sprintf(
            "%s %s %s for (host_parent_name in %s) to %s {\n",
            $this->getObjectTypeName(),
            $this->getType(),
            c::renderString($this->getObjectName()),
            $this->get('parent_host_var'),
            ucfirst($to)
        );
    }

    /**
     * @return string
     */
    protected function renderSuffix()
    {
        if (! $this->parentHostIsVar()) {
            return parent::renderSuffix();
        }

        if ((string) $this->get('assign_filter') !== '') {
            $suffix = parent::renderSuffix();
        } else {
            $suffix = '    assign where ' . $this->renderAssignFilterExtension('')
                . "\n" . parent::renderSuffix();
        }

        if ($this->renderApplyForArray) {
            return $suffix;
        }

        return $suffix . $this->renderApplyForArrayClone();
    }

    protected function renderApplyForArrayClone()
    {
        $clone = clone($this);
        $clone->renderApplyForArray = true;

        return $clone->toConfigString();
    }

    public function isApplyForArrayClone()
    {
        return $this->renderApplyForArray;
    }

    /**
     * @codingStandardsIgnoreStart
     */
    public function renderAssign_Filter()
    {
        if ($this->parentHostIsVar()) {
            return preg_replace(
                '/\n$/m',
                $this->renderAssignFilterExtension() . "\n",
                parent::renderAssign_Filter()
            );
        }

        return parent::renderAssign_Filter();
    }

    protected function renderAssignFilterExtension($pre = ' && ')
    {
        $varName = $this->get('parent_host_var');
        if ($this->renderApplyForArray) {
            return sprintf('%stypeof(%s) == Array', $pre, $varName);
        }

        return sprintf('%stypeof(%s) == String', $pre, $varName);
    }

    protected function setKey($key)
    {
        // TODO: Check if this method can be removed
        if (is_int($key)) {
            $this->id = $key;
        } elseif (is_array($key)) {
            $keys = [
                'id',
                'parent_host_id',
                'parent_service_id',
                'child_host_id',
                'child_service_id',
                'object_name'
            ];

            foreach ($keys as $k) {
                if (array_key_exists($k, $key)) {
                    $this->set($k, $key[$k]);
                }
            }
        } else {
            return parent::setKey($key);
        }

        return $this;
    }

    protected function renderAssignments()
    {
        // TODO: this will never be reached
        if ($this->hasBeenAssignedToServiceApply()) {
            /** @var IcingaService $tmpService */
            $tmpService = $this->getRelatedObject(
                'child_service',
                $this->get('child_service_id')
            );
            // TODO: fix this, will crash:
            $assigns = $tmpService->assignments()->toConfigString();

            $filter = sprintf(
                '%s && service.name == "%s"',
                trim($assigns),
                $this->get('child_service')
            );
            return "\n    " . $filter . "\n";
        }

        if ($this->hasBeenAssignedToHostTemplateService()) {
            $filter = sprintf(
                'assign where "%s" in host.templates && service.name == "%s"',
                $this->get('child_host'),
                $this->get('child_service')
            );
            return "\n    " . $filter . "\n";
        }
        if ($this->hasBeenAssignedToHostTemplate()) {
            $filter = sprintf(
                'assign where "%s" in host.templates',
                $this->get('child_host')
            );
            return "\n    " . $filter . "\n";
        }

        if ($this->hasBeenAssignedToServiceTemplate()) {
            $filter = sprintf(
                'assign where "%s" in service.templates',
                $this->get('child_service')
            );
            return "\n    " . $filter . "\n";
        }

        return parent::renderAssignments();
    }

    protected function hasBeenAssignedToHostTemplate()
    {
        try {
            $id = $this->get('child_host_id');
            return $id && $this->getRelatedObject(
                'child_host',
                $id
            )->isTemplate();
        } catch (NotFoundError $e) {
            return false;
        }
    }

    protected function hasBeenAssignedToServiceTemplate()
    {
        try {
            $id = $this->get('child_service_id');
            return $id && $this->getRelatedObject(
                'child_service',
                $id
            )->isTemplate();
        } catch (NotFoundError $e) {
            return false;
        }
    }

    protected function hasBeenAssignedToHostTemplateService()
    {
        if (!$this->hasBeenAssignedToHostTemplate()) {
            return false;
        }
        try {
            $id = $this->get('child_service_id');
            return $id && $this->getRelatedObject(
                'child_service',
                $id
            )->isObject();
        } catch (NotFoundError $e) {
            return false;
        }
    }

    protected function hasBeenAssignedToServiceApply()
    {
        try {
            $id = $this->get('child_service_id');
            return $id && $this->getRelatedObject(
                'child_service',
                $id
            )->isApplyRule();
        } catch (NotFoundError $e) {
            return false;
        }
    }

    /**
     * Render child_host_id as host_name
     *
     * Avoid complaints for method names with underscore:
     * @codingStandardsIgnoreStart
     *
     * @return string
     */
    public function renderChild_host_id()
    {
        // @codingStandardsIgnoreEnd

        if ($this->hasBeenAssignedToHostTemplate()) {
            return '';
        }

        return $this->renderRelationProperty(
            'child_host',
            $this->get('child_host_id'),
            'child_host_name'
        );
    }

    /**
     * Render parent_host_id as parent_host_name
     *
     * Avoid complaints for method names with underscore:
     * @codingStandardsIgnoreStart
     *
     * @return string
     */
    public function renderParent_host_id()
    {
        // @codingStandardsIgnoreEnd
        return $this->renderRelationProperty(
            'parent_host',
            $this->get('parent_host_id'),
            'parent_host_name'
        );
    }

    /**
     * Render parent_host_var as parent_host
     * @codingStandardsIgnoreStart
     *
     * @return string
     */
    public function renderParent_host_var()
    {
        // @codingStandardsIgnoreEnd
        if ($this->renderApplyForArray) {
            return c::renderKeyValue(
                'parent_host_name',
                'host_parent_name'
            );
        }

        // @codingStandardsIgnoreEnd
        return c::renderKeyValue(
            'parent_host_name',
            $this->get('parent_host_var')
        );
    }

    /**
     * Render child_service_id as host_name
     *
     * Avoid complaints for method names with underscore:
     * @codingStandardsIgnoreStart
     *
     * @return string
     */
    public function renderChild_service_id()
    {
        // @codingStandardsIgnoreEnd
        if ($this->hasBeenAssignedToServiceTemplate()
            || $this->hasBeenAssignedToHostTemplateService()
            || $this->hasBeenAssignedToServiceApply()
        ) {
            return '';
        }

        return $this->renderRelationProperty(
            'child_service',
            $this->get('child_service_id'),
            'child_service_name'
        );
    }

    /**
     * Render parent_service_id as parent_service_name
     *
     * Avoid complaints for method names with underscore:
     * @codingStandardsIgnoreStart
     *
     * @return string
     */
    public function renderParent_service_id()
    {
        return $this->renderRelationProperty(
            'parent_service',
            $this->get('parent_service_id'),
            'parent_service_name'
        );
    }

    //
    /**
     * Render parent_service_by_name as parent_service_name
     *
     * Special case for parent service set as plain string for Apply rules
     *
     * @codingStandardsIgnoreStart
     *
     * @return string
     */
    public function renderParent_service_by_name()
    {
        // @codingStandardsIgnoreEnd
        return c::renderKeyValue(
            'parent_service_name',
            c::renderString($this->get('parent_service_by_name'))
        );
    }

    public function isApplyRule()
    {
        if ($this->hasBeenAssignedToHostTemplate()
            || $this->hasBeenAssignedToServiceTemplate()
            || $this->hasBeenAssignedToServiceApply()
        ) {
            return true;
        }

        return parent::isApplyRule();
    }

    protected function resolveUnresolvedRelatedProperty($name)
    {
        $short = substr($name, 0, -3);
        /** @var IcingaObject|string $class */
        $class = $this->getRelationClass($short);
        $objKey = $this->unresolvedRelatedProperties[$name];

        # related services need array key
        if ($class === IcingaService::class) {
            if ($name === 'parent_service_id' && $this->get('object_type') === 'apply') {
                //special case , parent service can be set as simple string for Apply
                if ($this->properties['parent_host_id'] === null) {
                    $this->reallySet(
                        'parent_service_by_name',
                        $this->unresolvedRelatedProperties[$name]
                    );
                    $this->reallySet('parent_service_id', null);
                    unset($this->unresolvedRelatedProperties[$name]);
                    return;
                }
            }

            $this->reallySet('parent_service_by_name', null);
            $hostIdProperty = str_replace('service', 'host', $name);
            if (isset($this->properties[$hostIdProperty])) {
                $objKey = [
                    'host_id'     => $this->properties[$hostIdProperty],
                    'object_name' => $this->unresolvedRelatedProperties[$name]
                ];
            } else {
                $objKey = [
                    'host_id'     => null,
                    'object_name' => $this->unresolvedRelatedProperties[$name]
                ];
            }

            try {
                $class::load($objKey, $this->connection);
            } catch (NotFoundError $e) {
                // Not a simple service on host
                // Hunt through inherited services, use service assigned to
                // template if found
                $tmpHost = IcingaHost::loadWithAutoIncId(
                    $this->properties[$hostIdProperty],
                    $this->connection
                );

                // services for applicable templates
                $resolver = $tmpHost->templateResolver();
                foreach ($resolver->fetchResolvedParents() as $template_obj) {
                    $objKey = [
                        'host_id'     => $template_obj->id,
                        'object_name' => $this->unresolvedRelatedProperties[$name]
                    ];
                    try {
                        $object = $class::load($objKey, $this->connection);
                    } catch (NotFoundError $e) {
                        continue;
                    }
                    break;
                }

                if (!isset($object)) {
                    // Not an inherited service, now try apply rules
                    $matcher = HostApplyMatches::prepare($tmpHost);
                    foreach ($this->getAllApplyRules() as $rule) {
                        if ($matcher->matchesFilter($rule->filter)) {
                            if ($rule->name === $this->unresolvedRelatedProperties[$name]) {
                                $object = IcingaService::loadWithAutoIncId(
                                    $rule->id,
                                    $this->connection
                                );
                                break;
                            }
                        }
                    }
                }
            }
        } else {
            $object = $class::load($objKey, $this->connection);
        }

        if (isset($object)) {
            $this->reallySet($name, $object->get('id'));
            unset($this->unresolvedRelatedProperties[$name]);
        } else {
            // Depend on a single service on a single host. Rare case, as usually you want to
            // depend on a service on the very same host - and leave the Host field empty. The
            // latter is already being handled above. This duplicates some code, but I'll leave
            // it this way for now. There might have been a reason for the parent_host_id = null
            // check in that code.
            if ($name === 'parent_service_id' && $this->get('object_type') === 'apply') {
                $this->reallySet(
                    'parent_service_by_name',
                    $this->unresolvedRelatedProperties[$name]
                );
                $this->reallySet('parent_service_id', null);
                unset($this->unresolvedRelatedProperties[$name]);
                return;
            }
            throw new NotFoundError(sprintf(
                'Unable to resolve related property: %s "%s"',
                $name,
                $this->unresolvedRelatedProperties[$name]
            ));
        }
    }

    protected function getAllApplyRules()
    {
        $allApplyRules = $this->fetchAllApplyRules();
        foreach ($allApplyRules as $rule) {
            $rule->filter = Filter::fromQueryString($rule->assign_filter);
        }

        return $allApplyRules;
    }

    protected function fetchAllApplyRules()
    {
        $db = $this->connection->getDbAdapter();
        $query = $db->select()->from(['s' => 'icinga_service'], [
            'id'            => 's.id',
            'name'          => 's.object_name',
            'assign_filter' => 's.assign_filter',
        ])->where('object_type = ? AND assign_filter IS NOT NULL', 'apply');

        return $db->fetchAll($query);
    }

    protected function getRelatedProperty($key)
    {
        $related = parent::getRelatedProperty($key);
        // handle special case for plain string parent service on Dependency
        // Apply rules
        if ($related === null && $key === 'parent_service'
            && null !== $this->get('parent_service_by_name')
        ) {
            return $this->get('parent_service_by_name');
        }

        return $related;
    }
}
