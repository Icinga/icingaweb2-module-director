<?php

namespace Icinga\Module\Director\Web\Tabs;

use Icinga\Authentication\Auth;
use Icinga\Module\Director\Objects\IcingaObject;
use dipl\Translation\TranslationHelper;
use dipl\Web\Widget\Tabs;

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
        if ($auth->hasPermission("director/${plType}")) {
            $this->add('index', array(
                'url'   => sprintf('director/%s', $plType),
                'label' => $this->translate(ucfirst($plType)),
            ));
        }

        if ($object->getShortTableName() === 'command') {
            $this->add('external', array(
                'url'   => sprintf('director/%s', strtolower($plType)),
                'urlParams' => ['type' => 'external_object'],
                'label' => $this->translate('External'),
            ));
        }

        if ($auth->hasPermission('director/admin') || (
            $object->getShortTableName() === 'notification'
            && $auth->hasPermission('director/notifications')
        )) {
            if ($object->supportsApplyRules()) {
                $this->add('applyrules', array(
                    'url' => sprintf('director/%s/applyrules', $plType),
                    'label' => $this->translate('Apply')
                ));
            }
        }

        if ($auth->hasPermission('director/admin') && $type !== 'zone') {
            if ($object->supportsImports()) {
                $this->add('templates', array(
                    'url' => sprintf('director/%s/templates', $plType),
                    'label' => $this->translate('Templates'),
                ));
            }

            if ($object->supportsGroups()) {
                $this->add('groups', array(
                    'url' => sprintf('director/%sgroups', $typeUrl),
                    'label' => $this->translate('Groups')
                ));
            }
        }

        if ($auth->hasPermission('director/admin')) {
            if ($object->supportsChoices()) {
                $this->add('choices', array(
                    'url' => sprintf('director/templatechoices/%s', $shortName),
                    'label' => $this->translate('Choices')
                ));
            }
        }
        if ($object->supportsSets() && $auth->hasPermission("director/${typeUrl}sets")) {
            $this->add('sets', array(
                'url'    => sprintf('director/%s/sets', $plType),
                'label' => $this->translate('Sets')
            ));
        }
    }
}
