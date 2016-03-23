<?php

namespace Icinga\Module\Director\Controllers;

use Icinga\Module\Director\IcingaConfig\IcingaConfig;
use Icinga\Module\Director\Util;
use Icinga\Module\Director\Web\Controller\ActionController;
use Icinga\Web\Notification;
use Icinga\Web\Url;
use Exception;

class ConfigController extends ActionController
{
    protected $isApified = true;

    public function deploymentsAction()
    {
        $this->setAutorefreshInterval(5);
        try {
            if ($this->getRequest()->getUrl()->shift('checkforchanges')) {
                $this->api()->collectLogFiles($this->db());
            }
        } catch (Exception $e) {
            // No problem, Icinga might be reloading
        }
        $this->view->addLink = $this->view->qlink(
            $this->translate('Render config'),
            'director/config/store',
            null,
            array('class' => 'icon-wrench')
        );

        $this->overviewTabs()->activate('deploymentlog');
        $this->view->title = $this->translate('Deployments');
        $this->prepareTable('deploymentLog');
        try {
            // Move elsewhere
            $this->view->table->setActiveStageName(
                $this->api()->getActiveStageName()
            );
        } catch (Exception $e) {
            // Don't care
        }

        $this->render('objects/table', null, 'objects');
    }

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

        $this->api()->wipeInactiveStages($this->db());

        if ($this->api()->dumpConfig($config, $this->db())) {
            if ($isApiRequest) {
                return $this->sendJson((object) array('checksum' => $checksum));
            } else {
                $url = Url::fromPath('director/config/deployments?checkforchanges');
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

    public function activitiesAction()
    {
        $this->setAutorefreshInterval(10);
        $this->overviewTabs()->activate('activitylog');
        $this->view->title = $this->translate('Activity Log');
        $lastDeployedId = $this->db()->getLastDeploymentActivityLogId();
        $this->prepareTable('activityLog');
        $this->view->table->setLastDeployedId($lastDeployedId);
        $this->render('list/table', null, true);
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

        if ($deploymentId) {
            $this->view->table->setDeploymentId($deploymentId);
        }

        $this->view->config = IcingaConfig::load(
            Util::hex2binary($this->params->get('checksum')),
            $this->db()
        );
    }

    // Show a single file
    public function fileAction()
    {
        $tabs = $this->configTabs()->add('file', array(
            'label'     => $this->translate('Rendered file'),
            'url'       => $this->getRequest()->getUrl(),
        ))->activate('file');

        $this->view->addLink = $this->view->qlink(
            $this->translate('back'),
            'director/config/files',
            $this->getConfigTabParams(),
            array('class' => 'icon-left-big')
        );

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
        $this->configTabs()->activate('config');
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

    protected function overviewTabs()
    {
        $this->view->tabs = $this->getTabs()->add(
            'deploymentlog',
            array(
                'label' => $this->translate('Deployments'),
                'url'   => 'director/config/deployments'
            )
        )->add(
            'activitylog',
            array(
                'label' => $this->translate('Activity Log'),
                'url'   => 'director/config/activities'
            )
        );
        return $this->view->tabs;
    }

    protected function configTabs()
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
            'url'       => 'director/config/files',
            'urlParams' => $this->getConfigTabParams()
        ));

        return $tabs;
    }

    protected function getConfigTabParams()
    {
        $params = array(
            'checksum' => $this->params->get(
                'config_checksum',
                $this->params->get('checksum')
            )
        );

        if ($deploymentId = $this->params->get('deployment_id')) {
            $params['deployment_id'] = $deploymentId;
        }

        return $params;
    }
}
