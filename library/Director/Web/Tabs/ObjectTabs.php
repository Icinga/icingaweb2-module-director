<?php

namespace Icinga\Module\Director\Web\Tabs;

use Icinga\Authentication\Auth;
use Icinga\Module\Director\Objects\IcingaObject;
use dipl\Translation\TranslationHelper;
use dipl\Web\Widget\Tabs;

class ObjectTabs extends Tabs
{
    use TranslationHelper;

    /** @var string */
    private $type;

    /** @var Auth */
    private $auth;

    /** @var IcingaObject $object */
    private $object;

    private $allowedExternals = [
        'apiuser',
        'endpoint'
    ];

    public function __construct($type, Auth $auth, IcingaObject $object = null)
    {
        $this->type = $type;
        $this->auth = $auth;
        $this->object = $object;
        // We are not a BaseElement, not yet
        $this->assemble();
    }

    protected function assemble()
    {
        if (null === $this->object) {
            $this->addTabsForNewObject();
        } else {
            $this->addTabsForExistingObject();
        }
    }

    protected function addTabsForNewObject()
    {
        $type = $this->type;
        $this->add('add', array(
            'url'       => sprintf('director/%s/add', $type),
            'label'     => sprintf($this->translate('Add %s'), ucfirst($type)),
        ));
    }

    protected function addTabsForExistingObject()
    {
        $type = $this->type;
        $auth = $this->auth;
        $object = $this->object;
        $params = $object->getUrlParams();

        if (! $object->isExternal()
            || in_array($object->getShortTableName(), $this->allowedExternals)
        ) {
            $this->add('modify', array(
                'url'       => sprintf('director/%s', $type),
                'urlParams' => $params,
                'label'     => $this->translate(ucfirst($type))
            ));
        }
        if ($object->getShortTableName() === 'host') {
            $this->add('services', [
                'url' => 'director/host/services',
                'urlParams' => $params,
                'label' => $this->translate('Services')
            ]);
        }

        if ($auth->hasPermission('director/showconfig')) {
            if ($object->getShortTableName() !== 'service'
                || $object->get('service_set_id') === null
            ) {
                $this->add('render', array(
                    'url'       => sprintf('director/%s/render', $type),
                    'urlParams' => $params,
                    'label'     => $this->translate('Preview'),
                ));
            }
        }

        if ($auth->hasPermission('director/audit')) {
            $this->add('history', array(
                'url'       => sprintf('director/%s/history', $type),
                'urlParams' => $params,
                'label'     => $this->translate('History')
            ));
        }

        if ($auth->hasPermission('director/admin') && $this->hasFields()) {
            $this->add('fields', array(
                'url'       => sprintf('director/%s/fields', $type),
                'urlParams' => $params,
                'label'     => $this->translate('Fields')
            ));
        }

        // TODO: remove table check once we resolve all group types
        if ($object->isGroup() &&
            ($object->getShortTableName() === 'hostgroup' || $object->getShortTableName() === 'servicegroup')
        ) {
            $this->add('membership', [
                'url'       => sprintf('director/%s/membership', $type),
                'urlParams' => $params,
                'label'     => $this->translate('Members')
            ]);
        }

        if ($object->supportsRanges()) {
            $this->add('ranges', [
                'url'       => 'director/timeperiod/ranges',
                'urlParams' => $params,
                'label'     => $this->translate('Ranges')
            ]);
        }

        if ($object->getShortTableName() === 'endpoint'
            && $object->get('apiuser_id')
        ) {
            $this->add('inspect', [
                'url'       => 'director/inspect/types',
                'urlParams' => ['endpoint' => $object->getObjectName()],
                'label'     => $this->translate('Inspect')
            ]);
        }

        if ($object->getShortTableName() === 'host') {
            $this->add('agent', [
                'url' => 'director/host/agent',
                'urlParams' => $params,
                'label' => $this->translate('Agent')
            ]);
        }
    }

    protected function hasFields()
    {
        if (! ($object = $this->object)) {
            return false;
        }

        return $object->hasBeenLoadedFromDb()
            && $object->supportsFields()
            && ($object->isTemplate() || $this->type === 'command');
    }
}
