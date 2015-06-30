<?php

use Icinga\Module\Director\IcingaConfig\IcingaConfig;
use Icinga\Module\Director\Util;
use Icinga\Module\Director\Web\Controller\ActionController;
use Icinga\Web\Url;

class Director_ConfigController extends ActionController
{
    public function showAction()
    {
        $this->view->config = IcingaConfig::fromDb(Util::hex2binary($this->params->get('checksum')), $this->db());
    }

    public function storeAction()
    {
        $config = IcingaConfig::generate($this->db());
        $this->redirectNow(
            Url::fromPath('director/config/show',
            array('checksum' => $config->getHexChecksum()))
        );
    }
}
