<?php

use Icinga\Module\Director\IcingaConfig\IcingaConfig;
use Icinga\Module\Director\Util;
use Icinga\Module\Director\Web\Controller\ActionController;
use Icinga\Web\Url;
use Icinga\Module\Director\Core\RestApiClient;
use Icinga\Module\Director\Core\CoreApi;
use Icinga\Web\Notification;

class Director_ConfigController extends ActionController
{
    public function deployAction()
    {
        $checksum = $this->params->get('checksum');
        $config = IcingaConfig::fromDb(Util::hex2binary($checksum), $this->db());
        if ($this->api()->dumpConfig($config, $this->db())) {
            $url = Url::fromPath('director/list/deploymentlog');
            Notification::success(
                $this->translate('Config has been submitted, validation is going on')
            );
            $this->redirectNow($url);
        } else {
            $url = Url::fromPath('director/config/show', array('checksum' => $checksum));
            Notification::success(
                $this->translate('Config deployment failed')
            );
            $this->redirectNow($url);
        }
    }

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

    protected function api()
    {
        $apiconfig = $this->Config()->getSection('api');
        $client = new RestApiClient($apiconfig->get('address'), $apiconfig->get('port'));
        $client->setCredentials($apiconfig->get('username'), $apiconfig->get('password'));
        $api = new CoreApi($client);
        return $api;
    }
}
