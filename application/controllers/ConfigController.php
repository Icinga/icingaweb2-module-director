<?php

namespace Icinga\Module\Director\Controllers;

use Icinga\Module\Director\IcingaConfig\IcingaConfig;
use Icinga\Module\Director\Util;
use Icinga\Module\Director\Web\Controller\ActionController;
use Icinga\Web\Notification;
use Icinga\Web\Url;

class ConfigController extends ActionController
{
    protected $isApified = true;

    public function deployAction()
    {
        // TODO: require POST
        $isApiRequest = $this->getRequest()->isApiRequest();
        $checksum = $this->params->get('checksum');
        if ($checksum) {
            $config = IcingaConfig::load(Util::hex2binary($checksum), $this->db());
        } else {
            $config = IcingaConfig::generate($this->db());
            $checksum = $config->getHexChecksum();
        }

        if ($this->api()->dumpConfig($config, $this->db())) {
            if ($isApiRequest) {
                return $this->sendJson((object) array('checksum' => $checksum));
            } else {
                $url = Url::fromPath('director/list/deploymentlog');
                Notification::success(
                    $this->translate('Config has been submitted, validation is going on')
                );
                $this->redirectNow($url);
            }
        } else {
            if ($isApiRequest) {
                return $this->sendJsonError('Config deployment failed');
            } else {
                $url = Url::fromPath('director/config/show', array('checksum' => $checksum));
                Notification::success(
                    $this->translate('Config deployment failed')
                );
                $this->redirectNow($url);
            }
        }
    }

    // Show all files for a given config
    public function filesAction()
    {
        $this->view->title = $this->translate('Generated config');
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

        $this->view->config = IcingaConfig::load(
            Util::hex2binary($this->params->get('checksum')),
            $this->db()
        );
    }

    // Show a single file
    public function fileAction()
    {
        $this->view->config = IcingaConfig::load(Util::hex2binary($this->params->get('config_checksum')), $this->db());
        $filename = $this->view->filename = $this->params->get('file_path');
        $this->view->title = sprintf(
            $this->translate('Config file "%s"'),
            $filename
        );
        $this->view->file = $this->view->config->getFile($filename);
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
            Url::fromPath(
                'director/config/show',
                array('checksum' => $config->getHexChecksum())
            )
        );
    }
}
