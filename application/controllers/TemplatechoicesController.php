<?php

namespace Icinga\Module\Director\Controllers;

use Icinga\Module\Director\Web\ActionBar\ChoicesActionBar;
use Icinga\Module\Director\Web\Controller\ActionController;
use Icinga\Module\Director\Web\Table\ChoicesTable;
use Icinga\Module\Director\Web\Tabs\ObjectsTabs;

class TemplatechoicesController extends ActionController
{
    protected function checkDirectorPermissions()
    {
        $this->assertPermission('director/admin');
    }

    public function hostAction()
    {
        $this->prepare('host', $this->translate('Host template choices'));
    }

    public function serviceAction()
    {
        $this->prepare('service', $this->translate('Service template choices'));
    }

    public function notificationAction()
    {
        $this->prepare('notification', $this->translate('Notification template choices'));
    }

    protected function prepare($type, $title)
    {
        $this->tabs(new ObjectsTabs($type, $this->Auth()))->activate('choices');
        $this->actions(new ChoicesActionBar($type, $this->url()));
        $this->setAutorefreshInterval(10)->addTitle($title);
        ChoicesTable::create($type, $this->db())->renderTo($this);
    }
}
