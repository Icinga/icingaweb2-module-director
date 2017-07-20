<?php

namespace Icinga\Module\Director\Web\Widget;

use Icinga\Authentication\Auth;
use Icinga\Module\Director\IcingaConfig\IcingaConfig;
use Icinga\Module\Director\Objects\DirectorDeploymentLog;
use Icinga\Module\Director\StartupLogRenderer;
use Icinga\Util\Format;
use Icinga\Web\Request;
use ipl\Html\Html;
use ipl\Html\Icon;
use ipl\Html\Link;
use ipl\Translation\TranslationHelper;
use ipl\Web\Widget\NameValueTable;
use ipl\Web\Widget\Tabs;

class DeploymentInfo extends Html
{
    use TranslationHelper;

    /** @var DirectorDeploymentLog */
    protected $deployment;

    /** @var IcingaConfig */
    protected $config;

    public function __construct(DirectorDeploymentLog $deployment)
    {
        $this->deployment = $deployment;
        $this->config = IcingaConfig::load(
            $deployment->config_checksum,
            $deployment->getConnection()
        );
    }

    public function getTabs(Auth $auth, Request $request)
    {
        $dep = $this->deployment;
        $tabs = new Tabs();
        $tabs->add('deployment', array(
            'label' => $this->translate('Deployment'),
            'url'   => $request->getUrl()
        ))->activate('deployment');

        if ($dep->config_checksum !== null && $auth->hasPermission('director/showconfig')) {
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
        $table = new NameValueTable();
        $table->addNameValuePairs([
            $this->translate('Deployment time') => $dep->start_time,
            $this->translate('Sent to')         => $dep->peer_identity,
            $this->translate('Configuration')   => $this->getConfigDetails(),
            $this->translate('Duration')        => $this->getDurationInfo(),
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
        } elseif ($dep->startup_succeeded === 'y') {
            return $this->colored('green', [$this->translate('Succeeded'), new Icon('ok')]);
        } else {
            return $this->colored('red', [$this->translate('Failed'), new Icon('cancel')]);
        }
    }

    protected function colored($color, array $content)
    {
        return Html::tag('div', ['style' => "color: $color;"], $content)->setSeparator(' ');
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
        $this->add(Html::h2($this->translate('Startup Log')));
        $this->add(
            Html::tag('pre', [
                'class' => 'logfile'
            ], new StartupLogRenderer($this->deployment))
        );
    }
}
