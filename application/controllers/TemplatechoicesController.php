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
        $this->prepareTabs('host')
             ->addTitle($this->translate('Host template choices'))
             ->prepareActions('host');

        ChoicesTable::create('host', $this->db())->renderTo($this);
    }

    public function serviceAction()
    {
        $this->prepareTabs('service')
             ->addTitle($this->translate('Service template choices'))
             ->prepareActions('service');
        ChoicesTable::create('service', $this->db())->renderTo($this);
    }

    public function notificationAction()
    {
        $this->prepareTabs('notification')
             ->addTitle($this->translate('Notification template choices'))
             ->prepareActions('notification');
        ChoicesTable::create('notification', $this->db())->renderTo($this);
    }

    protected function prepareTabs($type)
    {
        $this->tabs(
            new ObjectsTabs($type, $this->Auth())
        )->activate('choices');

        return $this;
    }

    protected function prepareActions($type)
    {
        $this->actions(new ChoicesActionBar($type, $this->url()));

        return $this;
    }
}
