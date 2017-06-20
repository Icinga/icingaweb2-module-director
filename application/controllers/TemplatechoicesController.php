<?php

namespace Icinga\Module\Director\Controllers;

use Icinga\Module\Director\Web\Controller\ActionController;
use Icinga\Module\Director\Web\Table\ChoicesTable;

class TemplatechoicesController extends ActionController
{
    protected function checkDirectorPermissions()
    {
        $this->assertPermission('director/admin');
    }

    public function hostAction()
    {
        $this->addSingleTab('Choices')
             ->addTitle($this->translate('Host template choices'));
        ChoicesTable::create('host', $this->db())->renderTo($this);
    }

    public function serviceAction()
    {
        $this->addSingleTab('Choices')
             ->addTitle($this->translate('Service template choices'));
        ChoicesTable::create('service', $this->db())->renderTo($this);
    }

    public function notificationAction()
    {
        $this->addSingleTab('Choices')
             ->addTitle($this->translate('Notification template choices'));
        ChoicesTable::create('notification', $this->db())->renderTo($this);
    }
}
