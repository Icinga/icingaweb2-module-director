<?php

namespace Icinga\Module\Director\Controllers;

use Icinga\Module\Director\Web\Tabs\MainTabs;
use ipl\Html\Html;
use Icinga\Module\Director\Web\Widget\HealthCheckPluginOutput;
use Icinga\Module\Director\Health;
use Icinga\Module\Director\Web\Controller\ActionController;

class HealthController extends ActionController
{
    public function indexAction()
    {
        $this->setAutorefreshInterval(10);
        $this->tabs(new MainTabs($this->Auth(), $this->getDbResourceName()))->activate('health');
        $this->setTitle($this->translate('Director Health'));
        $health = new Health();
        $health->setDbResourceName($this->getDbResourceName());
        $output = new HealthCheckPluginOutput($health);
        $this->content()->add($output);
        $this->content()->add([
            Html::tag('h1', ['class' => 'icon-pin'], $this->translate('Hint: Check Plugin')),
            Html::tag('p', $this->translate(
                'Did you know that you can run this entire Health Check'
                . ' (or just some sections) as an Icinga Check on a regular'
                . ' base?'
            ))
        ]);
    }
}
