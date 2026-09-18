<?php

// SPDX-FileCopyrightText: 2018 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Icinga\Module\Director\Controllers;

use Exception;
use Icinga\Module\Director\Forms\KickstartForm;
use Icinga\Module\Director\Web\Controller\BranchHelper;

class KickstartController extends DashboardController
{
    use BranchHelper;

    public function indexAction()
    {
        $this->addSingleTab($this->translate('Kickstart'))
            ->addTitle($this->translate('Director Kickstart Wizard'));
        if ($this->showNotInBranch($this->translate('Kickstart'))) {
            return;
        }
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
