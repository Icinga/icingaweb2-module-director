<?php

namespace Icinga\Module\Director\Controllers;

use Icinga\Data\Filter\Filter;
use Icinga\Module\Director\ConfigDiff;
use Icinga\Module\Director\Forms\DeployConfigForm;
use Icinga\Module\Director\Forms\SettingsForm;
use Icinga\Module\Director\IcingaConfig\IcingaConfig;
use Icinga\Module\Director\Objects\DirectorDeploymentLog;
use Icinga\Module\Director\Settings;
use Icinga\Module\Director\Web\Table\ActivityLogTable;
use Icinga\Module\Director\Web\Table\ConfigFileDiffTable;
use Icinga\Module\Director\Web\Table\DeploymentLogTable;
use Icinga\Module\Director\Web\Table\GeneratedConfigFileTable;
use Icinga\Module\Director\Util;
use Icinga\Module\Director\Web\Controller\ActionController;
use Icinga\Module\Director\Web\Tabs\InfraTabs;
use Icinga\Module\Director\Web\Widget\ActivityLogInfo;
use Icinga\Module\Director\Web\Widget\DeployedConfigInfoHeader;
use Icinga\Module\Director\Web\Widget\ShowConfigFile;
use Icinga\Web\Notification;
use Icinga\Web\Url;
use Exception;
use ipl\Html\Html;
use ipl\Html\HtmlString;
use ipl\Html\Icon;
use ipl\Html\Link;

class ConfigController extends ActionController
{
    protected $isApified = true;

    protected function checkDirectorPermissions()
    {
    }

    public function deploymentsAction()
    {
        $this->assertPermission('director/deploy');
        $this->addTitle($this->translate('Deployments'));
        try {
            if (DirectorDeploymentLog::hasUncollected($this->db())) {
                $this->setAutorefreshInterval(3);
                $this->api()->collectLogFiles($this->db());
            } else {
                $this->setAutorefreshInterval(20);
            }
        } catch (Exception $e) {
            // No problem, Icinga might be reloading
        }

        // TODO: a form!
        $this->actions()->add(Link::create(
            $this->translate('Render config'),
            'director/config/store',
            null,
            ['class' => 'icon-wrench']
        ));

        $this->tabs(new InfraTabs($this->Auth()))->activate('deploymentlog');
        $table = new DeploymentLogTable($this->db());
        try {
            // Move elsewhere
            $table->setActiveStageName(
                $this->api()->getActiveStageName()
            );
        } catch (Exception $e) {
            // Don't care
        }

        $table->renderTo($this);
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

    public function activitiesAction()
    {
        $this->assertPermission('director/audit');

        $this->setAutorefreshInterval(10);
        $this->tabs(new InfraTabs($this->Auth()))->activate('activitylog');
        $this->addTitle($this->translate('Activity Log'));
        $lastDeployedId = $this->db()->getLastDeploymentActivityLogId();
        $table = new ActivityLogTable($this->db());
        $table->setLastDeployedId($lastDeployedId);
        $filter = Filter::fromQueryString(
            $this->url()->without(['page', 'limit', 'q'])->getQueryString()
        );
        $table->applyFilter($filter);
        if ($this->url()->hasParam('author')) {
            $this->actions()->add(Link::create(
                $this->translate('All changes'),
                $this->url()
                    ->without(['author', 'page']),
                null,
                ['class' => 'icon-users', 'data-base-target' => '_self']
            ));
        } else {
            $this->actions()->add(Link::create(
                $this->translate('My changes'),
                $this->url()
                    ->with('author', $this->Auth()->getUser()->getUsername())
                    ->without('page'),
                null,
                ['class' => 'icon-user', 'data-base-target' => '_self']
            ));
        }
        if ($this->hasPermission('director/deploy')) {
            $this->actions()->add(DeployConfigForm::load()
                ->setDb($this->db())
                ->setApi($this->api())
                ->handleRequest());
        }

        $table->renderTo($this);
    }

    public function activityAction()
    {
        $this->assertPermission('director/showconfig');
        $p = $this->params;
        $info = new ActivityLogInfo(
            $this->db(),
            $p->get('type'),
            $p->get('name')
        );

        $info->setChecksum($p->get('checksum'))
            ->setId($p->get('id'));

        $this->tabs($info->getTabs($this->url()));
        $info->showTab($this->params->get('show'));

        $this->addTitle($info->getTitle());
        $this->controls()->prepend($info->getPagination($this->url()));
        $this->content()->add($info);
    }

    public function settingsAction()
    {
        $this->assertPermission('director/admin');

        $this->addSingleTab($this->translate('Settings'))
            ->addTitle($this->translate('Global Director Settings'));
        $this->content()->add(
            SettingsForm::load()
            ->setSettings(new Settings($this->db()))
            ->handleRequest()
        );
    }

    /**
     * Show all files for a given config
     */
    public function filesAction()
    {
        $this->assertPermission('director/showconfig');
        $config = IcingaConfig::load(
            Util::hex2binary($this->params->getRequired('checksum')),
            $this->db()
        );
        $deploymentId = $this->params->get('deployment_id');

        $tabs = $this->tabs();
        if ($deploymentId) {
            $tabs->add('deployment', [
                'label'     => $this->translate('Deployment'),
                'url'       => 'director/deployment',
                'urlParams' => ['id' => $deploymentId]
            ]);
        }

        $tabs->add('config', [
            'label' => $this->translate('Config'),
            'url'   => $this->url(),
        ])->activate('config');

        $this->addTitle($this->translate('Generated config'));
        $this->content()->add(new DeployedConfigInfoHeader(
            $config,
            $this->db(),
            $this->api(),
            $deploymentId
        ));

        GeneratedConfigFileTable::load($config, $this->db())
            ->setActiveFilename($this->params->get('active_file'))
            ->setDeploymentId($deploymentId)
            ->renderTo($this);
    }

    /**
     * Show a single file
     */
    public function fileAction()
    {
        $this->assertPermission('director/showconfig');
        $filename = $this->params->getRequired('file_path');
        $this->configTabs()->add('file', array(
            'label' => $this->translate('Rendered file'),
            'url'   => $this->url(),
        ))->activate('file');

        $params = $this->getConfigTabParams();
        if ('deployment' === $this->params->get('backTo')) {
            $this->addBackLink('director/deployment', ['id' => $params['deployment_id']]);
        } else {
            $params['active_file'] = $filename;
            $this->addBackLink('director/config/files', $params);
        }

        $config = IcingaConfig::load(Util::hex2binary($this->params->get('config_checksum')), $this->db());
        $this->addTitle($this->translate('Config file "%s"'), $filename);
        $this->content()->add(new ShowConfigFile(
            $config->getFile($filename),
            $this->params->get('highlight'),
            $this->params->get('highlightSeverity')
        ));
    }

    // TODO: Check if this can be removed
    public function storeAction()
    {
        $this->assertPermission('director/deploy');
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
        $this->addTitle($this->translate('Config diff'));
        $this->addSingleTab($this->translate('Config diff'));

        $leftSum  = $this->params->get('left');
        $rightSum = $this->params->get('right');

        $configs = $db->enumDeployedConfigs();
        foreach (array($leftSum, $rightSum) as $sum) {
            if (! array_key_exists($sum, $configs)) {
                $configs[$sum] = substr($sum, 0, 7);
            }
        }

        $this->content()->add(Html::form(['action' => $this->url(), 'method' => 'GET'], [
            new HtmlString($this->view->formSelect(
                'left',
                $leftSum,
                ['class' => 'autosubmit', 'style' => 'width: 37%'],
                [null => $this->translate('- please choose -')] + $configs
            )),
            Link::create(
                Icon::create('flapping'),
                $this->url(),
                ['left' => $rightSum, 'right' => $leftSum]
            ),
            new HtmlString($this->view->formSelect(
                'right',
                $rightSum,
                ['class' => 'autosubmit', 'style' => 'width: 37%'],
                [null => $this->translate('- please choose -')] + $configs
            )),
        ]));

        if (! strlen($rightSum) || ! strlen($leftSum)) {
            return;
        }
        ConfigFileDiffTable::load($leftSum, $rightSum, $this->db())->renderTo($this);
    }

    public function filediffAction()
    {
        $this->assertPermission('director/showconfig');

        $p = $this->params;
        $db = $this->db();
        $leftSum  = $p->getRequired('left');
        $rightSum = $p->getRequired('right');
        $filename = $p->getRequired('file_path');

        $left = IcingaConfig::load(Util::hex2binary($leftSum), $db);
        $right = IcingaConfig::load(Util::hex2binary($rightSum), $db);

        $this
            ->addTitle($this->translate('Config file "%s"'), $filename)
            ->addSingleTab($this->translate('Diff'))
            ->content()->add(ConfigDiff::create(
                $left->getFile($filename),
                $right->getFile($filename)
            ));
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

    protected function configTabs()
    {
        $tabs = $this->tabs();

        if ($this->hasPermission('director/deploy')
            && $deploymentId = $this->params->get('deployment_id')
        ) {
            $tabs->add('deployment', [
                'label'     => $this->translate('Deployment'),
                'url'       => 'director/deployment',
                'urlParams' => ['id' => $deploymentId]
            ]);
        }

        if ($this->hasPermission('director/showconfig')) {
            $tabs->add('config', [
                'label'     => $this->translate('Config'),
                'url'       => 'director/config/files',
                'urlParams' => $this->getConfigTabParams()
            ]);
        }

        return $tabs;
    }

    protected function getConfigTabParams()
    {
        $params = [
            'checksum' => $this->params->get(
                'config_checksum',
                $this->params->get('checksum')
            )
        ];

        if ($deploymentId = $this->params->get('deployment_id')) {
            $params['deployment_id'] = $deploymentId;
        }

        return $params;
    }
}
