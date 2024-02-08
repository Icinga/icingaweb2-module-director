<?php

namespace Icinga\Module\Director\Web\Tabs;

use gipfl\Translation\TranslationHelper;
use gipfl\IcingaWeb2\Widget\Tabs;
use Icinga\Authentication\Auth;
use Icinga\Module\Director\Auth\Permission;
use Icinga\Module\Director\Web\Widget\Daemon\BackgroundDaemonState;
use Icinga\Module\Director\Db;
use Icinga\Module\Director\Health;
use Icinga\Module\Director\Web\Widget\HealthCheckPluginOutput;

class MainTabs extends Tabs
{
    use TranslationHelper;

    protected $auth;

    protected $dbResourceName;

    public function __construct(Auth $auth, $dbResourceName)
    {
        $this->auth = $auth;
        $this->dbResourceName = $dbResourceName;
        $this->add('main', [
            'label' => $this->translate('Overview'),
            'url' => 'director'
        ]);
        if ($this->auth->hasPermission(Permission::ADMIN)) {
            $this->add('health', [
                'label' => $this->translate('Health'),
                'url' => 'director/health'
            ])->add('daemon', [
                'label' => $this->translate('Daemon'),
                'url' => 'director/daemon'
            ]);
        }
    }

    public function render()
    {
        if ($this->auth->hasPermission(Permission::ADMIN)) {
            if ($this->getActiveName() !== 'health') {
                $state = $this->getHealthState();
                if ($state->isProblem()) {
                    $this->get('health')->setTagParams([
                        'class' => 'state-' . strtolower($state->getName())
                    ]);
                }
            }

            if ($this->getActiveName() !== 'daemon') {
                try {
                    $daemon = new BackgroundDaemonState(Db::fromResourceName($this->dbResourceName));
                    if ($daemon->isRunning()) {
                        $state = 'ok';
                    } else {
                        $state = 'critical';
                    }
                } catch (\Exception $e) {
                    $state = 'unknown';
                }
                if ($state !== 'ok') {
                    $this->get('daemon')->setTagParams([
                        'class' => 'state-' . $state
                    ]);
                }
            }
        }

        return parent::render();
    }

    /**
     * @return \Icinga\Module\Director\CheckPlugin\PluginState
     */
    protected function getHealthState()
    {
        $health = new Health();
        $health->setDbResourceName($this->dbResourceName);
        $output = new HealthCheckPluginOutput($health);

        return $output->getState();
    }
}
