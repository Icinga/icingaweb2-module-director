<?php

namespace Icinga\Module\Director\Web\Widget;

use Icinga\Module\Director\Auth\Permission;
use ipl\Html\HtmlDocument;
use Icinga\Authentication\Auth;
use Icinga\Module\Director\IcingaConfig\IcingaConfig;
use Icinga\Module\Director\Objects\DirectorDeploymentLog;
use Icinga\Module\Director\StartupLogRenderer;
use Icinga\Util\Format;
use Icinga\Web\Request;
use ipl\Html\Html;
use gipfl\IcingaWeb2\Icon;
use gipfl\IcingaWeb2\Link;
use gipfl\Translation\TranslationHelper;
use gipfl\IcingaWeb2\Widget\NameValueTable;
use gipfl\IcingaWeb2\Widget\Tabs;

class DeploymentInfo extends HtmlDocument
{
    use TranslationHelper;

    /** @var DirectorDeploymentLog */
    protected $deployment;

    /** @var IcingaConfig */
    protected $config;

    /**
     * DeploymentInfo constructor.
     * @param DirectorDeploymentLog $deployment
     */
    public function __construct(DirectorDeploymentLog $deployment)
    {
        $this->deployment = $deployment;
        if ($deployment->get('config_checksum') !== null) {
            $this->config = IcingaConfig::load(
                $deployment->get('config_checksum'),
                $deployment->getConnection()
            );
        }
    }

    /**
     * @param Auth $auth
     * @param Request $request
     * @return Tabs
     */
    public function getTabs(Auth $auth, Request $request)
    {
        $dep = $this->deployment;
        $tabs = new Tabs();
        $tabs->add('deployment', array(
            'label' => $this->translate('Deployment'),
            'url'   => $request->getUrl()
        ))->activate('deployment');

        if ($dep->config_checksum !== null && $auth->hasPermission(Permission::SHOW_CONFIG)) {
            $tabs->add('config', array(
                'label'     => $this->translate('Config'),
                'url'       => 'director/config/files',
                'urlParams' => array(
                    'checksum'      => $this->config->getHexChecksum(),
                    'deployment_id' => $dep->id
                )
            ));
        }

        return $tabs;
    }

    protected function createInfoTable()
    {
        $dep = $this->deployment;
        $table = (new NameValueTable())
            ->addAttributes(['class' => 'deployment-details']);
        $table->addNameValuePairs([
            $this->translate('Deployment time') => $dep->start_time,
            $this->translate('Sent to')         => $dep->peer_identity,
            $this->translate('Triggered by')    => $dep->username,
        ]);
        if ($this->config !== null) {
            $table->addNameValuePairs([
                $this->translate('Configuration')   => $this->getConfigDetails(),
                $this->translate('Duration')        => $this->getDurationInfo(),
            ]);
        }
        $table->addNameValuePairs([
            $this->translate('Stage name')      => $dep->stage_name,
            $this->translate('Startup')         => $this->getStartupInfo()
        ]);

        return $table;
    }

    protected function getDurationInfo()
    {
        return sprintf(
            $this->translate('Rendered in %0.2fs, deployed in %0.2fs'),
            $this->config->getDuration() / 1000,
            $this->deployment->duration_dump / 1000
        );
    }

    protected function getConfigDetails()
    {
        $cfg = $this->config;
        $dep = $this->deployment;

        return [
            Link::create(
                sprintf($this->translate('%d files'), $cfg->getFileCount()),
                'director/config/files',
                [
                    'checksum'      => $cfg->getHexChecksum(),
                    'deployment_id' => $dep->id
                ]
            ),
            ', ',
            sprintf(
                $this->translate('%d objects, %d templates, %d apply rules'),
                $cfg->getObjectCount(),
                $cfg->getTemplateCount(),
                $cfg->getApplyCount()
            ),
            ', ',
            Format::bytes($cfg->getSize())
        ];
    }

    protected function getStartupInfo()
    {
        $dep = $this->deployment;
        if ($dep->startup_succeeded === null) {
            if ($dep->stage_collected === null) {
                return [$this->translate('Unknown, still waiting for config check outcome'), new Icon('spinner')];
            } else {
                return [$this->translate('Unknown, failed to collect related information'), new Icon('help')];
            }
        } else {
            $div = Html::tag('div')->setSeparator(' ');

            if ($dep->startup_succeeded === 'y') {
                $div
                    ->addAttributes(['class' => 'succeeded'])
                    ->add([$this->translate('Succeeded'), new Icon('ok')]);
            } else {
                $div
                    ->addAttributes(['class' => 'failed'])
                    ->add([$this->translate('Failed'), new Icon('cancel')]);
            }

            return $div;
        }
    }

    public function render()
    {
        $this->add($this->createInfoTable());
        if ($this->deployment->get('startup_succeeded') !== null) {
            $this->addStartupLog();
        }

        return parent::render();
    }

    protected function addStartupLog()
    {
        $this->add(Html::tag('h2', null, $this->translate('Startup Log')));
        $this->add(
            Html::tag('pre', [
                'class' => 'logfile'
            ], new StartupLogRenderer($this->deployment))
        );
    }
}
