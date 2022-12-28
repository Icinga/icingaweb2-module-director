<?php

namespace Icinga\Module\Director\Controllers;

use gipfl\Web\Form;
use Icinga\Module\Director\RestApi\IcingaForWindowsApi;
use Icinga\Module\Director\Web\Controller\ActionController;
use Icinga\Module\Director\Windows\GeneratedDashboard;
use Icinga\Module\Director\Windows\RemoteMenu;

class WindowsController extends ActionController
{
    protected $isApified = true;

    public function init()
    {
        $handler = new IcingaForWindowsApi($this->getRequest(), $this->getResponse(), $this->db());
        if ($this->getRequest()->isApiRequest()) {
            $handler->dispatch();
            // TODO: Regular shutdown
            exit;
        } else {
            $this->addSingleTab($this->translate('Icinga for Windows'));
            $response = $handler->handleApiRequest();
            if ($response instanceof RemoteMenu) {
                $dashboard = GeneratedDashboard::create($response, $this->db());
                $this->content()->add($dashboard);
            } elseif ($response instanceof Form) {
                $this->content()->add($response);
            }
        }
    }

    public function indexAction()
    {
        // TODO: Remove actions
    }

    public function jobsAction()
    {
        // TODO: Remove actions
    }

    public function jobAction()
    {
        // TODO: Remove actions
    }
}
