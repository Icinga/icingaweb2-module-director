<?php

use Icinga\Module\Director\ActionController;

use Icinga\Module\Director\IcingaConfig\IcingaConfig;

class Director_ConfigController extends ActionController
{
    public function showAction()
    {
        /** @var IcingaConfig $config */
        $config = IcingaConfig::generate($this->db());
        $this->view->files = array();

        foreach ($config->getFiles() as $filename => $config) {
            $this->view->files[$filename] = $config->getContent();
        }
    }

    public function storeAction()
    {
        /** @var IcingaConfig $config */
        $config = IcingaConfig::generate($this->db());

        $config->store();
    }
}
