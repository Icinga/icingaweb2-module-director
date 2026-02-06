<?php

namespace Icinga\Module\Director\Controllers;

use gipfl\Diff\HtmlRenderer\SideBySideDiff;
use gipfl\Diff\PhpDiff;
use gipfl\Web\Widget\Hint;
use Icinga\Data\Filter\Filter;
use Icinga\Exception\IcingaException;
use Icinga\Exception\NotFoundError;
use Icinga\Module\Director\Auth\Permission;
use Icinga\Module\Director\Dashboard\Dashlet\DeploymentDashlet;
use Icinga\Module\Director\Db\Branch\Branch;
use Icinga\Module\Director\Deployment\DeploymentStatus;
use Icinga\Module\Director\Forms\DeployConfigForm;
use Icinga\Module\Director\Forms\SettingsForm;
use Icinga\Module\Director\IcingaConfig\IcingaConfig;
use Icinga\Module\Director\Objects\DirectorDeploymentLog;
use Icinga\Module\Director\Settings;
use Icinga\Module\Director\Web\Controller\BranchHelper;
use Icinga\Module\Director\Web\Table\ActivityLogTable;
use Icinga\Module\Director\Web\Table\BranchActivityTable;
use Icinga\Module\Director\Web\Table\ConfigFileDiffTable;
use Icinga\Module\Director\Web\Table\DeploymentLogTable;
use Icinga\Module\Director\Web\Table\GeneratedConfigFileTable;
use Icinga\Module\Director\Web\Controller\ActionController;
use Icinga\Module\Director\Web\Tabs\InfraTabs;
use Icinga\Module\Director\Web\Widget\ActivityLogInfo;
use Icinga\Module\Director\Web\Widget\DeployedConfigInfoHeader;
use Icinga\Module\Director\Web\Widget\ShowConfigFile;
use Icinga\Web\Notification;
use Exception;
use RuntimeException;
use ipl\Html\Html;
use ipl\Html\HtmlString;
use gipfl\IcingaWeb2\Icon;
use gipfl\IcingaWeb2\Link;
use gipfl\IcingaWeb2\Url;

class ConfigController extends ActionController
{
    use BranchHelper;

    protected $isApified = true;

    protected function checkDirectorPermissions()
    {
    }

    /**
     * @throws \Icinga\Security\SecurityException
     */
    public function deploymentsAction()
    {
        if ($this->sendNotFoundForRestApi()) {
            return;
        }
        $this->assertPermission('director/deploy');
        $this->addTitle($this->translate('Deployments'));
        try {
            if (DirectorDeploymentLog::hasUncollected($this->db())) {
                $this->setAutorefreshInterval(2);
            } else {
                $this->setAutorefreshInterval(20);
            }
        } catch (Exception $e) {
            $this->content()->prepend(Hint::warning($e->getMessage()));
            // No problem, Icinga might be reloading
        }

        if (! $this->getBranch()->isBranch()) {
            // TODO: a form!
            $this->actions()->add(Link::create(
                $this->translate('Render config'),
                'director/config/store',
                null,
                ['class' => 'icon-wrench']
            ));
        }

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

    /**
     * @throws NotFoundError
     * @throws \Icinga\Module\Director\Exception\DuplicateKeyException
     * @throws \Icinga\Security\SecurityException
     */
    public function deployAction()
    {
        $request = $this->getRequest();
        if (! $request->isApiRequest()) {
            throw new NotFoundError('Not found');
        }

        if (! $request->isPost()) {
            throw new RuntimeException(sprintf(
                'Unsupported method: %s',
                $request->getMethod()
            ));
        }
        $this->assertPermission('director/deploy');

        // TODO: require POST
        $checksum = $this->params->get('checksum');
        if ($checksum) {
            $config = IcingaConfig::load(hex2bin($checksum), $this->db());
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

    public function deploymentStatusAction()
    {
        if ($this->sendNotFoundUnlessRestApi()) {
            return;
        }
        $db = $this->db();
        $api = $this->api();
        $status = new DeploymentStatus($db, $api);
        $result = $status->getDeploymentStatus($this->params->get('configs'), $this->params->get('activities'));

        $this->sendJson($this->getResponse(), (object) $result);
    }

    /**
     * @throws \Icinga\Security\SecurityException
     */
    public function activitiesAction()
    {
        if ($this->sendNotFoundForRestApi()) {
            return;
        }
        $this->assertPermission('director/audit');
        $this->showOptionalBranchActivity();
        $this->setAutorefreshInterval(10);
        $this->tabs(new InfraTabs($this->Auth()))->activate('activitylog');
        $this->addTitle($this->translate('Activity Log'));
        $lastDeployedId = $this->db()->getLastDeploymentActivityLogId();
        $table = new ActivityLogTable($this->db());
        $table->setLastDeployedId($lastDeployedId);
        if ($idRangeEx = $this->url()->getParam('idRangeEx')) {
            $table->applyFilter(Filter::fromQueryString($idRangeEx));
        }
        $filter = Filter::fromQueryString(
            $this->url()->without(['page', 'limit', 'q', 'idRangeEx'])->getQueryString()
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
        if ($this->hasPermission(Permission::DEPLOY) && ! $this->getBranch()->isBranch()) {
            if ($this->db()->hasDeploymentEndpoint()) {
                $this->actions()->add(DeployConfigForm::load()
                    ->setDb($this->db())
                    ->setApi($this->api())
                    ->handleRequest());
            }

            if ((new DeploymentDashlet($this->db()))->lastDeploymentPending()) {
                $this->actions()->prependHtml(
                    Hint::warning($this->translate(
                        'There is an active deployment running, please wait until it is finished'
                        . ' before creating a new deployment.'
                    ))
                );
            }
        }

        $table->renderTo($this);
    }

    /**
     * @throws IcingaException
     * @throws \Icinga\Exception\Http\HttpNotFoundException
     * @throws \Icinga\Exception\ProgrammingError
     */
    public function activityAction()
    {
        if ($this->sendNotFoundForRestApi()) {
            return;
        }
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

    /**
     * @throws \Icinga\Security\SecurityException
     */
    public function settingsAction()
    {
        if ($this->sendNotFoundForRestApi()) {
            return;
        }
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
     *
     * @throws \Icinga\Exception\MissingParameterException
     * @throws \Icinga\Security\SecurityException
     */
    public function filesAction()
    {
        if ($this->sendNotFoundForRestApi()) {
            return;
        }
        $this->assertPermission('director/showconfig');
        $config = IcingaConfig::load(
            hex2bin($this->params->getRequired('checksum')),
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
            $this->getBranch(),
            $deploymentId
        ));

        GeneratedConfigFileTable::load($config, $this->db())
            ->setActiveFilename($this->params->get('active_file'))
            ->setDeploymentId($deploymentId)
            ->renderTo($this);
    }

    /**
     * Show a single file
     *
     * @throws \Icinga\Exception\MissingParameterException
     * @throws \Icinga\Security\SecurityException
     */
    public function fileAction()
    {
        if ($this->sendNotFoundForRestApi()) {
            return;
        }
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

        $config = IcingaConfig::load(hex2bin($this->params->get('config_checksum')), $this->db());
        $this->addTitle($this->translate('Config file "%s"'), $filename);
        $this->content()->add(new ShowConfigFile(
            $config->getFile($filename),
            $this->params->get('highlight'),
            $this->params->get('highlightSeverity')
        ));
    }

    /**
     * TODO: Check if this can be removed
     *
     * @throws \Icinga\Security\SecurityException
     */
    public function storeAction()
    {
        $this->assertPermission('director/deploy');
        try {
            $config = IcingaConfig::generate($this->db());
        } catch (Exception $e) {
            Notification::error($e->getMessage());
            $this->redirectNow('director/config/deployments');
        }
        $this->redirectNow(
            Url::fromPath(
                'director/config/files',
                array('checksum' => $config->getHexChecksum())
            )
        );
    }

    /**
     * @throws \Icinga\Security\SecurityException
     */
    public function diffAction()
    {
        if ($this->sendNotFoundForRestApi()) {
            return;
        }
        $this->assertPermission('director/showconfig');

        $db = $this->db();
        $this->addTitle($this->translate('Config diff'));
        $this->addSingleTab($this->translate('Config diff'));

        $leftSum  = $this->params->get('left');
        $rightSum = $this->params->get('right');

        $configs = $db->enumDeployedConfigs();
        foreach (array($leftSum, $rightSum) as $sum) {
            if ($sum && ! array_key_exists($sum, $configs)) {
                $configs[$sum] = substr($sum, 0, 7);
            }
        }

        $baseUrl = $this->url()->without(['left', 'right']);
        $this->content()->add(
            Html::tag('form', ['action' => (string) $baseUrl, 'method' => 'GET', 'class' => 'director-form'], [
                new HtmlString($this->view->formSelect(
                    'left',
                    $leftSum,
                    ['class' => ['autosubmit', 'config-diff']],
                    [null => $this->translate('- please choose -')] + $configs
                )),
                Link::create(
                    Icon::create('flapping'),
                    $baseUrl,
                    ['left' => $rightSum, 'right' => $leftSum]
                ),
                new HtmlString($this->view->formSelect(
                    'right',
                    $rightSum,
                    ['class' => ['autosubmit', 'config-diff']],
                    [null => $this->translate('- please choose -')] + $configs
                )),
            ])
        );

        if ($rightSum === null || $leftSum === null || ! strlen($rightSum) || ! strlen($leftSum)) {
            return;
        }
        ConfigFileDiffTable::load($leftSum, $rightSum, $this->db())->renderTo($this);
    }

    /**
     * @throws IcingaException
     * @throws \Icinga\Exception\MissingParameterException
     */
    public function filediffAction()
    {
        if ($this->sendNotFoundForRestApi()) {
            return;
        }
        $this->assertPermission('director/showconfig');

        $p = $this->params;
        $db = $this->db();
        $leftSum  = $p->getRequired('left');
        $rightSum = $p->getRequired('right');
        $filename = $p->getRequired('file_path');

        $left = IcingaConfig::load(hex2bin($leftSum), $db);
        $right = IcingaConfig::load(hex2bin($rightSum), $db);

        $this
            ->addTitle($this->translate('Config file "%s"'), $filename)
            ->addSingleTab($this->translate('Diff'))
            ->content()->add(new SideBySideDiff(new PhpDiff(
                $left->getFile($filename),
                $right->getFile($filename)
            )));
    }

    protected function showOptionalBranchActivity()
    {
        if ($this->url()->hasParam('idRangeEx')) {
            return;
        }
        $branch = $this->getBranch();
        if ($branch->isBranch() && (int) $this->params->get('page', '1') === 1) {
            $table = new BranchActivityTable($branch->getUuid(), $this->db());
            if (count($table) > 0) {
                $this->content()->add(Hint::info(Html::sprintf($this->translate(
                    'The following modifications are visible in this %s only...'
                ), Branch::requireHook()->linkToBranch(
                    $branch,
                    $this->Auth(),
                    $this->translate('configuration branch')
                ))));
                $this->content()->add($table);
                $this->content()->add(Html::tag('br'));
                $this->content()->add(Hint::ok($this->translate(
                    '...and the modifications below are already in the main branch:'
                )));
                $this->content()->add(Html::tag('br'));
            }
        }
    }

    /**
     * @param $checksum
     */
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

    /**
     * @param $checksum
     * @param null $error
     */
    protected function deploymentFailed($checksum, $error = null)
    {
        $extra = $error ? ': ' . $error : '';

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

    /**
     * @return \gipfl\IcingaWeb2\Widget\Tabs
     */
    protected function configTabs()
    {
        $tabs = $this->tabs();

        if (
            $this->hasPermission(Permission::DEPLOY)
            && $deploymentId = $this->params->get('deployment_id')
        ) {
            $tabs->add('deployment', [
                'label'     => $this->translate('Deployment'),
                'url'       => 'director/deployment',
                'urlParams' => ['id' => $deploymentId]
            ]);
        }

        if ($this->hasPermission(Permission::SHOW_CONFIG)) {
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
