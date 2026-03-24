<?php

// SPDX-FileCopyrightText: 2018 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Icinga\Module\Director\Controllers;

use Icinga\Module\Director\Web\Controller\ActionController;
use Icinga\Module\Director\Objects\DirectorDeploymentLog;
use Icinga\Module\Director\Web\Widget\DeploymentInfo;

class DeploymentController extends ActionController
{
    protected function checkDirectorPermissions()
    {
        $this->assertPermission('director/deploy');
    }

    public function indexAction()
    {
        $info = new DeploymentInfo(DirectorDeploymentLog::load(
            $this->params->get('id'),
            $this->db()
        ));
        $this->addTitle($this->translate('Deployment details'));
        $this->tabs(
            $info->getTabs($this->getAuth(), $this->getRequest())
        )->activate('deployment');
        $this->content()->add($info);
    }
}
