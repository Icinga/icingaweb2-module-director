<?php

use Icinga\Module\Director\ActionController;

use Icinga\Module\Director\IcingaConfig\IcingaConfig;

class Director_ConfigController extends ActionController
{
    public function showAction()
    {
        $this->view->config = IcingaConfig::fromDb(pack('H*', $this->params->get('checksum')), $this->db());
    }

    public function storeAction()
    {
        /** @var IcingaConfig $config */
        $config = IcingaConfig::generate($this->db());
        $this->view->id = $config->getHexChecksum();
    }
}
