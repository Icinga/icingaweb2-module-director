<?php

namespace Icinga\Module\Director\Dashboard;

class InfrastructureDashboard extends Dashboard
{
    protected $dashletNames = array(
        'Kickstart',
        'SelfService',
        'ApiUserObject',
        'EndpointObject',
        'ZoneObject',
    );

    public function getTitle()
    {
        return $this->translate('Manage your Icinga Infrastructure');
    }

    public function getDescription()
    {
        return $this->translate(
            'This is where you manage your Icinga 2 infrastructure. When adding'
            . ' a new Icinga Master or Satellite please re-run the Kickstart'
            . ' Helper once.'
            . "\n\n"
            . 'When you feel the desire to manually create Zone or Endpoint'
            . ' objects please rethink this twice. Doing so is mostly the wrong'
            . ' way, might lead to a dead end, requiring quite some effort to'
            . ' clean up the whole mess afterwards.'
        );
    }
}
