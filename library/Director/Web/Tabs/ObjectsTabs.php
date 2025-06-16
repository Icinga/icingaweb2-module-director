<?php

namespace Icinga\Module\Director\Web\Tabs;

use Icinga\Authentication\Auth;
use Icinga\Module\Director\Auth\Permission;
use Icinga\Module\Director\Objects\IcingaObject;
use gipfl\Translation\TranslationHelper;
use gipfl\IcingaWeb2\Widget\Tabs;

class ObjectsTabs extends Tabs
{
    use TranslationHelper;

    public function __construct($type, Auth $auth, $typeUrl)
    {
        $object = IcingaObject::createByType($type);
        if ($object->isGroup()) {
            $object = IcingaObject::createByType(substr($typeUrl, 0, -5));
        }
        $shortName = $object->getShortTableName();

        $plType = strtolower(preg_replace('/cys$/', 'cies', $shortName . 's'));
        $plType = str_replace('_', '-', $plType);
        if ($auth->hasPermission("director/{$plType}")) {
            $this->add('index', [
                'url'   => sprintf('director/%s', $plType),
                'label' => $this->translate(ucfirst($plType)),
            ]);
        }

        if ($object->getShortTableName() === 'command') {
            $this->add('external', [
                'url'       => sprintf('director/%s', strtolower($plType)),
                'urlParams' => ['type' => 'external_object'],
                'label'     => $this->translate('External'),
            ]);
        }

        if (
            $auth->hasPermission(Permission::ADMIN)
            || (
            $object->getShortTableName() === 'notification'
            && $auth->hasPermission(Permission::NOTIFICATIONS)
            ) || (
            $object->getShortTableName() === 'scheduled_downtime'
            && $auth->hasPermission(Permission::SCHEDULED_DOWNTIMES)
            )
        ) {
            if ($object->supportsApplyRules()) {
                $this->add('applyrules', [
                    'url'   => sprintf('director/%s/applyrules', $plType),
                    'label' => $this->translate('Apply')
                ]);
            }
        }

        if ($auth->hasPermission(Permission::ADMIN) && $type !== 'zone') {
            if ($object->supportsImports()) {
                $this->add('templates', [
                    'url'   => sprintf('director/%s/templates', $plType),
                    'label' => $this->translate('Templates'),
                ]);
            }

            if ($object->supportsGroups()) {
                $this->add('groups', [
                    'url'   => sprintf('director/%sgroups', $type),
                    'label' => $this->translate('Groups')
                ]);
            }
        }

        if ($auth->hasPermission(Permission::ADMIN)) {
            if ($object->supportsChoices()) {
                $this->add('choices', [
                    'url'   => sprintf('director/templatechoices/%s', $shortName),
                    'label' => $this->translate('Choices')
                ]);
            }
        }
        if ($object->supportsSets() && $auth->hasPermission("director/{$typeUrl}sets")) {
            $this->add('sets', [
                'url'   => sprintf('director/%s/sets', $plType),
                'label' => $this->translate('Sets')
            ]);
        }
    }
}
