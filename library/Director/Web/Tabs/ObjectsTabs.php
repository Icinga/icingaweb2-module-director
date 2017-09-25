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

        $pltype=strtolower(preg_replace('/cys$/', 'cies', $type . 's'));
        if ($auth->hasPermission("director/${pltype}")) {
            $this->add('index', array(
                'url'   => sprintf('director/%s', $pltype),
                'label' => $this->translate(ucfirst($pltype)),
            ));
        }

        if ($object->getShortTableName() === 'command') {
            $this->add('external', array(
                'url'   => sprintf('director/%s', strtolower($pltype)),
                'urlParams' => ['type' => 'external_object'],
                'label' => $this->translate('External'),
            ));
        }

        if ($auth->hasPermission('director/admin') || (
                $object->getShortTableName() === 'notifications' && $auth->hasPermission('director/notifications')
            )) {
            if ($object->supportsApplyRules()) {
                $this->add('applyrules', array(
                    'url' => sprintf('director/%s/applyrules', $pltype),
                    'label' => $this->translate('Apply')
                ));
            }
        }

        if ($auth->hasPermission('director/admin') && $type !== 'zone') {
            if ($object->supportsImports()) {
                $this->add('templates', array(
                    'url' => sprintf('director/%s/templates', $pltype),
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
                'url'    => sprintf('director/%s/sets', $pltype),
                'label' => $this->translate('Sets')
            ));
        }
    }
}
