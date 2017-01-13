<?php

namespace Icinga\Module\Director\Web\Controller;

use Icinga\Data\Paginatable;
use Icinga\Exception\AuthenticationException;
use Icinga\Exception\ConfigurationError;
use Icinga\Exception\NotFoundError;
use Icinga\Module\Director\Core\CoreApi;
use Icinga\Module\Director\Db;
use Icinga\Module\Director\IcingaConfig\IcingaConfig;
use Icinga\Module\Director\Monitoring;
use Icinga\Module\Director\Objects\IcingaEndpoint;
use Icinga\Module\Director\Objects\IcingaObject;
use Icinga\Module\Director\Web\Form\FormLoader;
use Icinga\Module\Director\Web\Form\QuickBaseForm;
use Icinga\Module\Director\Web\Table\QuickTable;
use Icinga\Module\Director\Web\Table\TableLoader;
use Icinga\Security\SecurityException;
use Icinga\Web\Controller;
use Icinga\Web\Widget;

abstract class ActionController extends Controller
{
    /** @var Db */
    protected $db;

    protected $isApified = false;

    /** @var CoreApi */
    private $api;

    /** @var Monitoring */
    private $monitoring;

    protected $icingaConfig;

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

        $this->checkDirectorPermissions();
    }

    protected function checkDirectorPermissions()
    {
        $this->assertPermission('director/admin');
    }

    /**
     * Assert that the current user has one of the given permission
     *
     * @param   array $permissions      Permission name list
     *
     * @throws  SecurityException       If the current user lacks the given permission
     */
    protected function assertOneOfPermissions($permissions)
    {
        $auth = $this->Auth();

        foreach ($permissions as $permission) {
            if ($auth->hasPermission($permission)) {
                return;
            }
        }

        throw new SecurityException(
            'Got none of the following permissions: %s',
            implode(', ', $permissions)
        );
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

    /**
     * @param string $name
     *
     * @return QuickBaseForm
     */
    public function loadForm($name)
    {
        $form = FormLoader::load($name, $this->Module());
        if ($this->getRequest()->isApiRequest()) {
            // TODO: Ask form for API support?
            $form->setApiRequest();
        }

        return $form;
    }

    /**
     * @param string $name
     *
     * @return QuickTable
     */
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

    protected function sendJsonError($message, $code = null)
    {
        if ($code !== null) {
            $this->setHttpResponseCode((int) $code);
        }

        $this->sendJson((object) array('error' => $message));
    }

    protected function singleTab($label)
    {
        return $this->view->tabs = Widget::create('tabs')->add(
            'tab',
            array(
                'label' => $label,
                'url'   => $this->getRequest()->getUrl()
            )
        )->activate('tab');
    }

    protected function setConfigTabs()
    {
        $this->view->tabs = Widget::create('tabs')->add(
            'deploymentlog',
            array(
                'label' => $this->translate('Deployments'),
                'url'   => 'director/list/deploymentlog'
            )
        )->add(
            'generatedconfig',
            array(
                'label' => $this->translate('Configs'),
                'url'   => 'director/list/generatedconfig'
            )
        )->add(
            'activitylog',
            array(
                'label' => $this->translate('Activity Log'),
                'url'   => 'director/list/activitylog'
            )
        );
        return $this->view->tabs;
    }

    protected function setDataTabs()
    {
        $this->view->tabs = Widget::create('tabs')->add(
            'datafield',
            array(
                'label' => $this->translate('Data fields'),
                'url'   => 'director/data/fields'
            )
        )->add(
            'datalist',
            array(
                'label' => $this->translate('Data lists'),
                'url'   => 'director/data/lists'
            )
        );
        return $this->view->tabs;
    }

    protected function setImportTabs()
    {
        $this->view->tabs = Widget::create('tabs')->add(
            'importsource',
            array(
                'label' => $this->translate('Import source'),
                'url'   => 'director/list/importsource'
            )
        )->add(
            'syncrule',
            array(
                'label' => $this->translate('Sync rule'),
                'url'   => 'director/list/syncrule'
            )
        )->add(
            'jobs',
            array(
                'label' => $this->translate('Jobs'),
                'url'   => 'director/jobs'
            )
        );
        return $this->view->tabs;
    }

    protected function provideQuickSearch()
    {
        $htm = '<form action="%s" class="quicksearch inline" method="post">'
             . '<input type="text" name="q" value="" placeholder="%s" class="search" />'
             . '</form>';

        $this->view->quickSearch = sprintf(
            $htm,
            $this->getRequest()->getUrl()->without(array('q', 'page', 'modifyFilter')),
            $this->translate('Search...')
        );

        return $this;
    }

    protected function shorten($string, $length)
    {
        if (strlen($string) > $length) {
            return substr($string, 0, $length) . '...';
        }
        return $string;
    }

    protected function setViewScript($name)
    {
        $this->_helper->viewRenderer->setNoController(true);
        $this->_helper->viewRenderer->setScriptAction($name);
    }

    protected function prepareTable($name)
    {
        $table = $this->loadTable($name)->setConnection($this->db());
        $this->view->filterEditor = $table->getFilterEditor($this->getRequest());
        $this->view->table = $this->applyPaginationLimits($table);
        return $this;
    }

    protected function prepareAndRenderTable($name)
    {
        $this->prepareTable($name)->setViewScript('list/table');
    }

    protected function provideFilterEditorForTable(QuickTable $table, IcingaObject $dummy = null)
    {
        $filterEditor = $table->getFilterEditor($this->getRequest());
        $filter = $filterEditor->getFilter();

        if ($filter->isEmpty()) {

            if ($this->params->get('modifyFilter')) {
                $this->view->addLink .= ' ' . $this->view->qlink(
                    $this->translate('Show unfiltered'),
                    $this->getRequest()->getUrl()->setParams(array()),
                    null,
                    array(
                        'class' => 'icon-cancel',
                        'data-base-target' => '_self',
                    )
                );
            } else {
                $this->view->addLink .= ' ' . $this->view->qlink(
                        $this->translate('Filter'),
                        $this->getRequest()->getUrl()->with('modifyFilter', true),
                        null,
                        array(
                            'class' => 'icon-search',
                            'data-base-target' => '_self',
                        )
                    );
            }

        } else {

            $this->view->addLink .= ' ' . $this->view->qlink(
                    $this->shorten($filter, 32),
                    $this->getRequest()->getUrl()->with('modifyFilter', true),
                    null,
                    array(
                        'class' => 'icon-search',
                        'data-base-target' => '_self',
                    )
                );

            $this->view->addLink .= ' ' . $this->view->qlink(
                    $this->translate('Show unfiltered'),
                    $this->getRequest()->getUrl()->setParams(array()),
                    null,
                    array(
                        'class' => 'icon-cancel',
                        'data-base-target' => '_self',
                    )
                );
        }

        if ($this->params->get('modifyFilter')) {
            $this->view->filterEditor = $filterEditor;
        }

        if ($this->getRequest()->isApiRequest()) {
            if ($dummy === null) {
                throw new NotFoundError('Not accessible via API');
            }

            $objects = array();
            foreach ($dummy::loadAll($this->db) as $object) {
                $objects[] = $object->toPlainObject(false, true);
            }
            return $this->sendJson((object) array('objects' => $objects));
        }

        $this->view->table = $this->applyPaginationLimits($table);
        $this->provideQuickSearch();
    }

    // TODO: just return json_last_error_msg() for PHP >= 5.5.0
    protected function getLastJsonError()
    {
        switch (json_last_error()) {
            case JSON_ERROR_DEPTH:
                return 'The maximum stack depth has been exceeded';
            case JSON_ERROR_CTRL_CHAR:
                return 'Control character error, possibly incorrectly encoded';
            case JSON_ERROR_STATE_MISMATCH:
                return 'Invalid or malformed JSON';
            case JSON_ERROR_SYNTAX:
                return 'Syntax error';
            case JSON_ERROR_UTF8:
                return 'Malformed UTF-8 characters, possibly incorrectly encoded';
            default:
                return 'An error occured when parsing a JSON string';
        }
    }

    protected function getApiIfAvailable()
    {
        if ($this->api === null) {
            if ($this->db->hasDeploymentEndpoint()) {
                $endpoint = $this->db()->getDeploymentEndpoint();
                $this->api = $endpoint->api();
            }
        }

        return $this->api;
    }

    protected function api($endpointName = null)
    {
        if ($this->api === null) {
            if ($endpointName === null) {
                $endpoint = $this->db()->getDeploymentEndpoint();
            } else {
                $endpoint = IcingaEndpoint::load($endpointName, $this->db());
            }

            $this->api = $endpoint->api();
        }

        return $this->api;
    }

    /**
     * @throws ConfigurationError
     *
     * @return Db
     */
    protected function db()
    {
        if ($this->db === null) {
            $resourceName = $this->Config()->get('db', 'resource');
            if ($resourceName) {
                $this->db = Db::fromResourceName($resourceName);
            } else {
                if ($this->getRequest()->isApiRequest()) {
                    throw new ConfigurationError('Icinga Director is not correctly configured');
                } else {
                    $this->redirectNow('director');
                }
            }
        }

        return $this->db;
    }

    /**
     * @return Monitoring
     */
    protected function monitoring()
    {
        if ($this->monitoring === null) {
            $this->monitoring = new Monitoring;
        }

        return $this->monitoring;
    }
}
