<?php

namespace Icinga\Module\Director\Web\Tabs;

use Icinga\Authentication\Auth;
use gipfl\Translation\TranslationHelper;
use gipfl\IcingaWeb2\Widget\Tabs;
use Icinga\Module\Director\Auth\Permission;

class InfraTabs extends Tabs
{
    use TranslationHelper;

    /** @var Auth */
    protected $auth;

    public function __construct(Auth $auth)
    {
        $this->auth = $auth;
        // We are not a BaseElement, not yet
        $this->assemble();
    }

    protected function assemble()
    {
        $auth = $this->auth;

        if ($auth->hasPermission(Permission::AUDIT)) {
            $this->add('activitylog', [
                'label' => $this->translate('Activity Log'),
                'url'   => 'director/config/activities'
            ]);
        }

        if ($auth->hasPermission(Permission::DEPLOY)) {
            $this->add('deploymentlog', [
                'label' => $this->translate('Deployments'),
                'url' => 'director/config/deployments'
            ]);
        }

        if ($auth->hasPermission(Permission::ADMIN)) {
            $this->add('infrastructure', [
                'label'     => $this->translate('Infrastructure'),
                'url'       => 'director/dashboard',
                'urlParams' => ['name' => 'infrastructure']
            ]);
        }
    }
}
