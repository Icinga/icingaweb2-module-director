<?php

use Icinga\Module\Director\ActionController;

use Icinga\Module\Director\IcingaConfig\IcingaConfig;

class Director_ConfController extends ActionController
{
    public function showAction()
    {
        $config = IcingaConfig::generate($this->db());
        $this->view->files = array();

        foreach ($config->getFiles() as $filename => $config) {
            $this->view->files[$filename] = $config->getContent();
        }
    }
}
