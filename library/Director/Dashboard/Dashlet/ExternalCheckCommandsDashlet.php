<?php

namespace Icinga\Module\Director\Dashboard\Dashlet;

use Icinga\Module\Director\Auth\Permission;

class ExternalCheckCommandsDashlet extends CheckCommandsDashlet
{
    protected $icon = 'download';

    public function getSummary()
    {
        return $this->translate(
            'External Commands have been defined in your local Icinga 2'
            . ' Configuration.'
        );
    }

    public function getTitle()
    {
        return $this->translate('External Commands');
    }

    public function listRequiredPermissions()
    {
        return [Permission::COMMAND_EXTERNAL];
    }

    public function getUrl()
    {
        return 'director/commands?type=external_object';
    }
}
