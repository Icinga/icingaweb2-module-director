<?php

namespace Icinga\Module\Director\Controllers;

use Icinga\Module\Director\IcingaConfig\IcingaConfig;
use Icinga\Module\Director\Util;
use Icinga\Module\Director\Web\Controller\ActionController;
use Icinga\Web\Notification;
use Icinga\Web\Url;

class ConfigController extends ActionController
{
    public function deployAction()
    {
        $checksum = $this->params->get('checksum');
        $config = IcingaConfig::load(Util::hex2binary($checksum), $this->db());
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

    // Show all files for a given config
    public function filesAction()
    {
        $tabs = $this->getTabs();

        if ($deploymentId = $this->params->get('deployment_id')) {
            $tabs->add('deployment', array(
                'label'     => $this->translate('Deployment'),
                'url'       => 'director/deployment',
                'urlParams' => array(
                    'id' => $deploymentId
                )
            ));
        }

        $tabs->add('config', array(
            'label' => $this->translate('Config'),
            'url'   => $this->getRequest()->getUrl(),
        ))->activate('config');

        $checksum = $this->params->get('checksum');

        $this->view->table = $this
            ->loadTable('GeneratedConfigFile')
            ->setConnection($this->db())
            ->setConfigChecksum($checksum);

        $this->render('objects/table', null, true);
    }

    public function showAction()
    {
        $tabs = $this->getTabs();

        if ($deploymentId = $this->params->get('deployment_id')) {
            $tabs->add('deployment', array(
                'label'     => $this->translate('Deployment'),
                'url'       => 'director/deployment/show',
                'urlParams' => array(
                    'id' => $deploymentId
                )
            ));
        }

        $tabs->add('config', array(
            'label'     => $this->translate('Config'),
            'url'       => $this->getRequest()->getUrl(),
        ))->activate('config');

        $this->view->config = IcingaConfig::load(Util::hex2binary($this->params->get('checksum')), $this->db());
    }

    // TODO: Check if this can be removed
    public function storeAction()
    {
        $config = IcingaConfig::generate($this->db());
        $this->redirectNow(
            Url::fromPath('director/config/show',
            array('checksum' => $config->getHexChecksum()))
        );
    }
}
