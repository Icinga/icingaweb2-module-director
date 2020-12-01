<?php

namespace Icinga\Module\Director\Controllers;

use gipfl\Web\Widget\Hint;
use Icinga\Application\Icinga;
use Icinga\Module\Director\Daemon\RunningDaemonInfo;
use Icinga\Module\Director\Web\Tabs\MainTabs;
use Icinga\Module\Director\Web\Controller\ActionController;
use Icinga\Module\Director\Web\Widget\BackgroundDaemonDetails;
use Icinga\Module\Director\Web\Widget\Documentation;
use ipl\Html\Html;

class DaemonController extends ActionController
{
    public function indexAction()
    {
        $this->setAutorefreshInterval(10);
        $this->tabs(new MainTabs($this->Auth(), $this->getDbResourceName()))->activate('daemon');
        $this->setTitle($this->translate('Director Background Daemon'));
        // Avoiding layout issues:
        $this->content()->add(Html::tag('h1', $this->translate('Director Background Daemon')));
        // TODO: move dashboard titles into controls. Or figure out whether 2.7 "broke" this

        $error = null;
        try {
            $db = $this->db()->getDbAdapter();
            $daemons = $db->fetchAll(
                $db->select()->from('director_daemon_info')->order('fqdn')->order('username')->order('pid')
            );
        } catch (\Exception $e) {
            $daemons = [];
            $error = $e->getMessage();
        }

        if (empty($daemons)) {
            $documentation = new Documentation(Icinga::app(), $this->Auth());
            $message = Html::sprintf($this->translate(
                'The Icinga Director Background Daemon is not running.'
                . ' Please check our %s in case you need step by step instructions'
                . ' showing you how to fix this.'
            ), $documentation->getModuleLink(
                $this->translate('documentation'),
                'director',
                '75-Background-Daemon',
                $this->translate('Icinga Director Background Daemon')
            ));
            $this->content()->add(Hint::error([
                $message,
                ($error ? [Html::tag('br'), Html::tag('strong', $error)] : null),
            ]));
            return;
        }

        try {
            foreach ($daemons as $daemon) {
                $info = new RunningDaemonInfo($daemon);
                $this->content()->add([new BackgroundDaemonDetails($info, $daemon)  /*, $logWindow*/]);
            }
        } catch (\Exception $e) {
            $this->content()->add(Hint::error($e->getMessage()));
        }
    }
}
