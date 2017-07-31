<?php

namespace Icinga\Module\Director\Dashboard\Dashlet;

class UserTemplateDashlet extends Dashlet
{
    protected $icon = 'cubes';

    protected $requiredStats = array('user');

    public function getTitle()
    {
        return $this->translate('User Templates');
    }

    public function getSummary()
    {
        return $this->translate('Provide templates for your User objects.')
            . ' ' . $this->getTemplateSummaryText('user');
    }

    public function listRequiredPermissions()
    {
        return array('director/admin');
    }

    public function getUrl()
    {
        return 'director/users/templates';
    }
}
