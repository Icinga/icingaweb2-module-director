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
        $this->prepareTabs($type)
            ->setAutorefreshInterval(10)
            ->addTitle($title)
            ->prepareActions($type);
        ChoicesTable::create($type, $this->db())->renderTo($this);
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
