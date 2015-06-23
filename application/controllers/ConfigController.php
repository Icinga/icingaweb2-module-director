<?php

use Icinga\Module\Director\ActionController;

use Icinga\Module\Director\IcingaConfig\IcingaConfig;
use Icinga\Module\Director\Util;

class Director_ConfigController extends ActionController
{
    public function showAction()
    {
        $this->view->config = IcingaConfig::fromDb(Util::hex2binary($this->params->get('checksum')), $this->db());
    }

    public function storeAction()
    {
        /** @var IcingaConfig $config */
        $config = IcingaConfig::generate($this->db());
        $this->view->id = $config->getHexChecksum();
    }
}
