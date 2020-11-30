<?php

namespace Icinga\Module\Director\Dashboard;

use gipfl\Web\Widget\Hint;
use Icinga\Application\Icinga;
use Icinga\Authentication\Auth;
use Icinga\Module\Director\Web\Tabs\InfraTabs;
use Icinga\Module\Director\Web\Widget\Documentation;
use ipl\Html\Html;
use ipl\Html\HtmlDocument;

class InfrastructureDashboard extends Dashboard
{
    protected $dashletNames = array(
        'Kickstart',
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
        $documentation = new Documentation(Icinga::app(), Auth::getInstance());

        $link = $documentation->getModuleLink(
            $this->translate('documentation'),
            'director',
            '24-Working-with-agents',
            $this->translate('Working with Agents and Config Zones')
        );
        return (new HtmlDocument())->add([
            $this->translate(
                'This is where you manage your Icinga 2 infrastructure. When adding'
                . ' a new Icinga Master or Satellite please re-run the Kickstart'
                . ' Helper once.'
            ),
            Hint::warning($this->translate(
                'When you feel the desire to manually create Zone or Endpoint'
                . ' objects please rethink this twice. Doing so is mostly the wrong'
                . ' way, might lead to a dead end, requiring quite some effort to'
                . ' clean up the whole mess afterwards.'
            )),
            Html::sprintf(
                $this->translate('Want to connect to your Icinga Agents? Have a look at our %s!'),
                $link
            )
        ]);
    }

    public function getTabs()
    {
        return new InfraTabs($this->getAuth());
    }
}
