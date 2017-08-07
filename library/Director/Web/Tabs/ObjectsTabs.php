<?php

namespace Icinga\Module\Director\Web\Tabs;

use Icinga\Authentication\Auth;
use Icinga\Module\Director\Objects\IcingaObject;
use ipl\Translation\TranslationHelper;
use ipl\Web\Widget\Tabs;

class ObjectsTabs extends Tabs
{
    use TranslationHelper;

    public function __construct($type, Auth $auth)
    {
        $object = IcingaObject::createByType($type);
        if ($object->isGroup()) {
            $object = IcingaObject::createByType(substr($type, 0, -5));
        }

        // TODO: plural?
        if ($auth->hasPermission("director/${type}s")) {
            $this->add('index', array(
                'url'   => sprintf('director/%ss', strtolower($type)),
                'label' => $this->translate(ucfirst($type) . 's'),
            ));
        }

        if ($object->getShortTableName() === 'command') {
            $this->add('external', array(
                'url'   => sprintf('director/%ss', strtolower($type)),
                'urlParams' => ['type' => 'external_object'],
                'label' => $this->translate('External'),
            ));
        }

        if ($auth->hasPermission('director/admin') || (
                $object->getShortTableName() === 'notifications' && $auth->hasPermission('director/notifications')
            )) {
            if ($object->supportsApplyRules()) {
                $this->add('applyrules', array(
                    'url' => sprintf('director/%ss/applyrules', $type),
                    'label' => $this->translate('Apply')
                ));
            }
        }

        if ($auth->hasPermission('director/admin')) {
            if ($object->supportsImports()) {
                $this->add('templates', array(
                    'url' => sprintf('director/%ss/templates', strtolower($type)),
                    'label' => $this->translate('Templates'),
                ));
            }

            if ($object->supportsGroups()) {
                $this->add('groups', array(
                    'url' => sprintf('director/%sgroups', $type),
                    'label' => $this->translate('Groups')
                ));
            }
        }

        if ($auth->hasPermission('director/admin')) {
            if ($object->supportsChoices()) {
                $this->add('choices', array(
                    'url' => sprintf('director/templatechoices/%s', $type),
                    'label' => $this->translate('Choices')
                ));
            }
        }
        if ($object->supportsSets() && $auth->hasPermission("director/${type}_sets")) {
            $this->add('sets', array(
                'url'    => sprintf('director/%ss/sets', $type),
                'label' => $this->translate('Sets')
            ));
        }
    }
}
