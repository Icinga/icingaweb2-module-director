<?php

namespace Icinga\Module\Director\Dashboard\Dashlet;

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

    public function getUrl()
    {
        return 'director/commands?type=external_object';
    }
}
