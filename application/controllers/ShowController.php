<?php

namespace Icinga\Module\Director\Controllers;

use Icinga\Module\Director\Web\Controller\ActionController;
use Icinga\Module\Director\Web\Widget\ActivityLogInfo;

class ShowController extends ActionController
{
    protected function checkDirectorPermissions()
    {
        $this->assertPermission('director/showconfig');
    }

    public function activitylogAction()
    {
        $p = $this->params;
        $info = new ActivityLogInfo(
            $this->db(),
            $p->get('type'),
            $p->get('name')
        );

        $info->setChecksum($p->get('checksum'))
             ->setId($p->get('id'));

        $this->tabs($info->getTabs($this->url()));
        $info->showTab($this->params->get('show'));

        $this->addTitle($info->getTitle());
        $this->controls()->prepend($info->getPagination($this->url()));
        $this->content()->add($info);
    }
}
