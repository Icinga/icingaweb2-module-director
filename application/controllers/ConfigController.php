<?php

namespace Icinga\Module\Director\Controllers;

use Icinga\Module\Director\ConfigDiff;
use Icinga\Module\Director\IcingaConfig\IcingaConfig;
use Icinga\Module\Director\Settings;
use Icinga\Module\Director\Util;
use Icinga\Module\Director\Web\Controller\ActionController;
use Icinga\Web\Notification;
use Icinga\Web\Url;
use Exception;

class ConfigController extends ActionController
{
    protected $isApified = true;

    protected function checkDirectorPermissions()
    {
    }

    public function deploymentsAction()
    {
        $this->assertPermission('director/deploy');
        try {
            if ($this->db()->hasUncollectedDeployments()) {
                $this->setAutorefreshInterval(5);
                $this->api()->collectLogFiles($this->db());
            } else {
                $this->setAutorefreshInterval(10);
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
        $this->assertPermission('director/deploy');

        // TODO: require POST
        $checksum = $this->params->get('checksum');
        if ($checksum) {
            $config = IcingaConfig::load(Util::hex2binary($checksum), $this->db());
        } else {
            $config = IcingaConfig::generate($this->db());
            $checksum = $config->getHexChecksum();
        }

        try {
            $this->api()->wipeInactiveStages($this->db());
        } catch (Exception $e) {
            $this->deploymentFailed($checksum, $e->getMessage());
        }

        if ($this->api()->dumpConfig($config, $this->db())) {
            $this->deploymentSucceeded($checksum);
        } else {
            $this->deploymentFailed($checksum);
        }
    }

    protected function deploymentSucceeded($checksum)
    {
        if ($this->getRequest()->isApiRequest()) {
            $this->sendJson($this->getResponse(), (object) array('checksum' => $checksum));
            return;
        } else {
            $url = Url::fromPath('director/config/deployments');
            Notification::success(
                $this->translate('Config has been submitted, validation is going on')
            );
            $this->redirectNow($url);
        }
    }

    protected function deploymentFailed($checksum, $error = null)
    {
        $extra = $error ? ': ' . $error: '';

        if ($this->getRequest()->isApiRequest()) {
            $this->sendJsonError($this->getResponse(), 'Config deployment failed' . $extra);
            return;
        } else {
            $url = Url::fromPath('director/config/files', array('checksum' => $checksum));
            Notification::error(
                $this->translate('Config deployment failed') . $extra
            );
            $this->redirectNow($url);
        }
    }

    public function activitiesAction()
    {
        $this->assertPermission('director/audit');

        $this->setAutorefreshInterval(10);
        $this->overviewTabs()->activate('activitylog');
        $this->view->title = $this->translate('Activity Log');
        $lastDeployedId = $this->db()->getLastDeploymentActivityLogId();
        $this->prepareTable('activityLog');
        $this->view->table->setLastDeployedId($lastDeployedId);
        $this->view->addLink = $this->view->qlink(
            $this->translate('My changes'),
            $this->getRequest()->getUrl()
                ->with('author', $this->Auth()->getUser()->getUsername())
                ->without('page'),
            null,
            array('class' => 'icon-user', 'data-base-target' => '_self')
        );
        if ($this->hasPermission('director/deploy')) {
            $this->view->addLink .= $this
                ->loadForm('DeployConfig')
                ->setDb($this->db())
                ->setApi($this->api())
                ->handleRequest();
        }
        $this->provideFilterEditorForTable($this->view->table);

        $this->setViewScript('list/table');
    }

    public function settingsAction()
    {
        $this->assertPermission('director/admin');

        $this->overviewTabs()->activate('settings');
        $this->view->title = $this->translate('Settings');
        $this->view->form = $this
            ->loadForm('Settings')
            ->setSettings(new Settings($this->db()))
            ->handleRequest();

        $this->setViewScript('object/form');
    }

    // Show all files for a given config
    public function filesAction()
    {
        $this->assertPermission('director/showconfig');

        $this->view->title = $this->translate('Generated config');
        $tabs = $this->getTabs();

        if ($deploymentId = $this->view->deploymentId = $this->params->get('deployment_id')) {
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

        $this->view->deployForm = $this->loadForm('DeployConfig')
            ->setAttrib('class', 'inline')
            ->setDb($this->db())
            ->setApi($this->api())
            ->setChecksum($checksum)
            ->setDeploymentId($deploymentId)
            ->handleRequest();

        $this->view->table = $this
            ->loadTable('GeneratedConfigFile')
            ->setActiveFilename($this->params->get('active_file'))
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
        $this->assertPermission('director/showconfig');
        $filename = $this->view->filename = $this->params->get('file_path');
        $fileOnly = $this->params->get('fileOnly');
        $this->view->highlight = $this->params->get('highlight');
        $this->view->highlightSeverity = $this->params->get('highlightSeverity');
        $tabs = $this->configTabs()->add('file', array(
            'label'     => $this->translate('Rendered file'),
            'url'       => $this->getRequest()->getUrl(),
        ))->activate('file');

        $params = $this->getConfigTabParams();
        if ('deployment' === $this->params->get('backTo')) {
            $this->view->addLink = $this->view->qlink(
                $this->translate('back'),
                'director/deployment',
                array('id' => $params['deployment_id']),
                array('class' => 'icon-left-big')
            );
        } else {
            $params['active_file'] = $filename;
            $this->view->addLink = $this->view->qlink(
                $this->translate('back'),
                'director/config/files',
                $params,
                array('class' => 'icon-left-big')
            );
        }

        $this->view->config = IcingaConfig::load(Util::hex2binary($this->params->get('config_checksum')), $this->db());
        $this->view->title = sprintf(
            $this->translate('Config file "%s"'),
            $filename
        );
        $this->view->file = $this->view->config->getFile($filename);
    }

    public function showAction()
    {
        $this->assertPermission('director/showconfig');

        $this->configTabs()->activate('config');
        $this->view->config = IcingaConfig::load(Util::hex2binary($this->params->get('checksum')), $this->db());
    }

    // TODO: Check if this can be removed
    public function storeAction()
    {
        $config = IcingaConfig::generate($this->db());
        $this->redirectNow(
            Url::fromPath(
                'director/config/files',
                array('checksum' => $config->getHexChecksum())
            )
        );
    }

    public function diffAction()
    {
        $this->assertPermission('director/showconfig');

        $db = $this->db();
        $this->view->title = $this->translate('Config diff');

        $tabs = $this->getTabs()->add('diff', array(
            'label'     => $this->translate('Config diff'),
            'url'       => $this->getRequest()->getUrl()
        ))->activate('diff');

        $leftSum  = $this->view->leftSum  = $this->params->get('left');
        $rightSum = $this->view->rightSum = $this->params->get('right');
        $left  = IcingaConfig::load(Util::hex2binary($leftSum), $db);

        $configs = $db->enumDeployedConfigs();
        foreach (array($leftSum, $rightSum) as $sum) {
            if (! array_key_exists($sum, $configs)) {
                $configs[$sum] = substr($sum, 0, 7);
            }
        }

        $this->view->configs = $configs;
        if ($rightSum === null) {
            return;
        }

        $right = IcingaConfig::load(Util::hex2binary($rightSum), $db);
        $this->view->table = $this
            ->loadTable('ConfigFileDiff')
            ->setConnection($this->db())
            ->setLeftChecksum($leftSum)
            ->setRightChecksum($rightSum);
    }

    public function filediffAction()
    {
        $this->assertPermission('director/showconfig');

        $db = $this->db();
        $leftSum  = $this->params->get('left');
        $rightSum = $this->params->get('right');
        $filename = $this->view->filename = $this->params->get('file_path');

        $left = IcingaConfig::load(Util::hex2binary($leftSum), $db);
        $right = IcingaConfig::load(Util::hex2binary($rightSum), $db);

        $leftFile  = $left->getFile($filename);
        $rightFile = $right->getFile($filename);

        $d = ConfigDiff::create($leftFile, $rightFile);

        $this->view->title = sprintf(
            $this->translate('Config file "%s"'),
            $filename
        );

        $this->view->output = $d->renderHtml();
    }

    protected function overviewTabs()
    {
        $this->view->tabs = $tabs = $this->getTabs();

        if ($this->hasPermission('director/audit')) {
            $tabs->add(
                'activitylog',
                array(
                    'label' => $this->translate('Activity Log'),
                    'url'   => 'director/config/activities'
                )
            );
        }

        if ($this->hasPermission('director/deploy')) {
            $tabs->add(
                'deploymentlog',
                array(
                    'label' => $this->translate('Deployments'),
                    'url' => 'director/config/deployments'
                )
            );
        }
        if ($this->hasPermission('director/admin')) {
            $tabs->add(
                'settings',
                array(
                    'label' => $this->translate('Settings'),
                    'url' => 'director/config/settings'
                )
            );
        }
        return $this->view->tabs;
    }

    protected function configTabs()
    {
        $tabs = $this->getTabs();

        if ($this->hasPermission('director/deploy') && $deploymentId = $this->params->get('deployment_id')) {
            $tabs->add('deployment', array(
                'label'     => $this->translate('Deployment'),
                'url'       => 'director/deployment',
                'urlParams' => array(
                    'id' => $deploymentId
                )
            ));
        }

        if ($this->hasPermission('director/showconfig')) {
            $tabs->add('config', array(
                'label'     => $this->translate('Config'),
                'url'       => 'director/config/files',
                'urlParams' => $this->getConfigTabParams()
            ));
        }

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
