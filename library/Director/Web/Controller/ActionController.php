<?php

namespace Icinga\Module\Director\Web\Controller;

use Icinga\Application\Icinga;
use Icinga\Data\Paginatable;
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

    protected function applyPaginationLimits(Paginatable $paginatable, $limit = 25, $offset = null)
    {
        $limit = $this->params->get('limit', $limit);
        $page = $this->params->get('page', $offset);

        $paginatable->limit($limit, $page > 0 ? ($page - 1) * $limit : 0);

        return $paginatable;
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
        )->add('datalist', array(
            'label' => $this->translate('Data lists'),
            'url'   => 'director/list/datalist')
        )->add('datafield', array(
            'label' => $this->translate('Data fields'),
            'url'   => 'director/list/datafield')
        );
        return $this->view->tabs;
    }

    protected function setImportTabs()
    {
        $this->view->tabs = Widget::create('tabs')->add('importsource', array(
            'label' => $this->translate('Import source'),
            'url'   => 'director/list/importsource')
        )->add('importrun', array(
            'label' => $this->translate('Import run'),
            'url'   => 'director/list/importrun')
        )->add('syncrule', array(
            'label' => $this->translate('Sync rule'),
            'url'   => 'director/list/syncrule')
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
