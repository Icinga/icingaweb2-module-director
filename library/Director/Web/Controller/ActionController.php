<?php

namespace Icinga\Module\Director\Web\Controller;

use Icinga\Application\Icinga;
use Icinga\Data\Paginatable;
use Icinga\Exception\AuthenticationException;
use Icinga\Exception\NotFoundError;
use Icinga\Module\Director\Core\RestApiClient;
use Icinga\Module\Director\Core\CoreApi;
use Icinga\Module\Director\Db;
use Icinga\Module\Director\Web\Form\FormLoader;
use Icinga\Module\Director\Web\Table\TableLoader;
use Icinga\Web\Controller;
use Icinga\Web\Widget;

abstract class ActionController extends Controller
{
    protected $db;

    protected $isApified = false;

    public function init()
    {
        if ($this->getRequest()->isApiRequest()) {
            if (! $this->hasPermission('director/api')) {
                throw new AuthenticationException('You are not allowed to access this API');
            }

            if (! $this->isApified()) {
                throw new NotFoundError('No such API endpoint found');
            }
        }
    }

    protected function isApified()
    {
        return $this->isApified;
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
        $form = FormLoader::load($name, $this->Module());
        if ($this->getRequest()->isApiRequest()) {
            // TODO: Ask form for API support?
            $form->setApiRequest();
        }

        return $form;
    }

    public function loadTable($name)
    {
        return TableLoader::load($name, $this->Module());
    }

    protected function sendJson($object)
    {
        $this->getResponse()->setHeader('Content-Type', 'application/json', true);
        $this->_helper->layout()->disableLayout(); 
        $this->_helper->viewRenderer->setNoRender(true);
        if (version_compare(PHP_VERSION, '5.4.0') >= 0) {
            echo json_encode($object, JSON_PRETTY_PRINT) . "\n";
        } else {
            echo json_encode($object);
        }
    }

    protected function setConfigTabs()
    {
        $this->view->tabs = Widget::create('tabs')->add('deploymentlog', array(
            'label' => $this->translate('Deployments'),
            'url'   => 'director/list/deploymentlog')
        )->add('generatedconfig', array(
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
            'label' => $this->translate('Import history'),
            'url'   => 'director/list/importrun')
        )->add('syncrule', array(
            'label' => $this->translate('Sync rule'),
            'url'   => 'director/list/syncrule')
        );
        return $this->view->tabs;
    }

    protected function api()
    {
        $apiconfig = $this->Config()->getSection('api');
        $client = new RestApiClient($apiconfig->get('address'), $apiconfig->get('port'));
        $client->setCredentials($apiconfig->get('username'), $apiconfig->get('password'));
        $api = new CoreApi($client);
        return $api;
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
