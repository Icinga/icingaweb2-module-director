<?php

use Icinga\Module\Director\ActionController;

use Icinga\Module\Director\IcingaConfig\IcingaConfig;

class Director_ShowController extends ActionController
{
    public function activitylogAction()
    {
        if ($id = $this->params->get('id')) {
            $this->view->entry = $this->db()->fetchActivityLogEntry($id);
            $this->view->title = $this->translate('Activity');
        }
    }

    public function configAction()
    {
        $config = IcingaConfig::fromDb($this->db());
        $this->view->files = array();

        foreach ($config->getFiles() as $filename => $config) {
            $this->view->files[$filename] = $config->getContent();
        }
    }
}
