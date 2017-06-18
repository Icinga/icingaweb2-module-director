<?php

namespace Icinga\Module\Director\Dashboard\Dashlet;

class ExternalCheckCommandsDashlet extends CheckCommandsDashlet
{
    protected $icon = 'wrench';

    public function getSummary()
    {
        return $this->translate(
            'External Check Commands have been defined in your local Icinga 2'
            . ' Configuration. '
        );
    }

    public function getTitle()
    {
        return $this->translate('External Check Commands');
    }
}
