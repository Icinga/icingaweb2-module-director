<?php

namespace Icinga\Module\Director\Controllers;

use Exception;
use Icinga\Module\Director\Forms\KickstartForm;

class KickstartController extends DashboardController
{
    public function indexAction()
    {
        $this->addSingleTab($this->translate('Kickstart'))
            ->addTitle($this->translate('Director Kickstart Wizard'));
        $form = KickstartForm::load();
        try {
            $form->setEndpoint($this->db()->getDeploymentEndpoint());
        } catch (Exception $e) {
            // Silently ignore DB errors
        }

        $form->handleRequest();
        $this->content()->add($form);
    }
}
