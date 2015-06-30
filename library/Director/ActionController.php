<?php

namespace Icinga\Module\Director;

use Icinga\Application\Icinga;
use Icinga\Module\Director\Db;
use Icinga\Module\Director\Web\Form\FormLoader;
use Icinga\Module\Director\Web\Table\TableLoader;
use Icinga\Web\Controller;
use Icinga\Web\Widget;

abstract class ActionController extends Controller
{
    protected $db;

    protected $forcedMonitoring = false;

    public function init()
    {
        // TODO: this is obsolete I guess
        $m = Icinga::app()->getModuleManager();
        if (! $m->hasLoaded('monitoring') && $m->hasInstalled('monitoring')) {
            $m->loadModule('monitoring');
        }
    }

    public function loadForm($name)
    {
        return FormLoader::load($name, $this->Module());
    }

    public function loadTable($name)
    {
        return TableLoader::load($name, $this->Module());
    }

    protected function setConfigTabs()
    {
        $this->view->tabs = Widget::create('tabs')->add('generatedconfig', array(
            'label' => $this->translate('Configs'),
            'url'   => 'director/list/generatedconfig')
        )->add('activitylog', array(
            'label' => $this->translate('Activity Log'),
            'url'   => 'director/list/activitylog')
        );
        return $this->view->tabs;
    }

    protected function setGlobalTabs()
    {
        $this->view->tabs = Widget::create('tabs')->add('commands', array(
            'label' => $this->translate('Commands'),
            'url'   => 'director/list/commands')
        )->add('commandarguments', array(
            'label' => $this->translate('(args)'),
            'url'   => 'director/list/commandarguments')
        )->add('timeperiods', array(
            'label' => $this->translate('Timeperiods'),
            'url'   => 'director/list/timeperiods')
        )->add('zones', array(
            'label' => $this->translate('Zones'),
            'url'   => 'director/list/zones')
        )->add('endpoints', array(
            'label' => $this->translate('(ep)'),
            'url'   => 'director/list/endpoints')
        );
        return $this->view->tabs;
    }

    protected function setHostTabs()
    {
        $this->view->tabs = Widget::create('tabs')->add('hosts', array(
            'label' => $this->translate('Hosts'),
            'url'   => 'director/list/hosts')
        )->add('hostgroups', array(
            'label' => $this->translate('Hostgroups'),
            'url'   => 'director/list/hostgroups')
        );
        return $this->view->tabs;
    }

    protected function setServiceTabs()
    {
        $this->view->tabs = Widget::create('tabs')->add('services', array(
            'label' => $this->translate('Services'),
            'url'   => 'director/list/services')
        )->add('servicegroups', array(
            'label' => $this->translate('Servicegroups'),
            'url'   => 'director/list/servicegroups')
        );
        return $this->view->tabs;
    }

    protected function setUserTabs()
    {
        $this->view->tabs = Widget::create('tabs')->add('users', array(
            'label' => $this->translate('Users'),
            'url'   => 'director/list/users')
        )->add('usergroups', array(
            'label' => $this->translate('Usergroups'),
            'url'   => 'director/list/usergroups')
        );
        return $this->view->tabs;
    }

    protected function db()
    {
        if ($this->db === null) {
            $resourceName = $this->Config()->get('db', 'resource');
            if ($resourceName) {
                $this->db = Db::fromResourceName($resourceName);
            } else {
                $this->redirectNow('director/welcome');
            }
        }

        return $this->db;
    }
}
