<?php

namespace Icinga\Module\Director\Web\Controller;

use Icinga\Application\Benchmark;
use Icinga\Data\Paginatable;
use Icinga\Exception\NotFoundError;
use Icinga\Module\Director\Db;
use Icinga\Module\Director\Monitoring;
use Icinga\Module\Director\Objects\IcingaObject;
use Icinga\Module\Director\Web\Controller\Extension\CoreApi;
use Icinga\Module\Director\Web\Controller\Extension\DirectorDb;
use Icinga\Module\Director\Web\Controller\Extension\RestApi;
use Icinga\Module\Director\Web\Form\FormLoader;
use Icinga\Module\Director\Web\Form\QuickBaseForm;
use Icinga\Module\Director\Web\Table\QuickTable;
use Icinga\Module\Director\Web\Table\TableLoader;
use Icinga\Security\SecurityException;
use Icinga\Web\Controller;
use Icinga\Web\Widget;
use ipl\Web\Component\ControlsAndContent;
use ipl\Web\Controller\Extension\ControlsAndContentHelper;
use ipl\Zf1\SimpleViewRenderer;

abstract class ActionController extends Controller implements ControlsAndContent
{
    use DirectorDb;
    use CoreApi;
    use RestApi;
    use ControlsAndContentHelper;

    protected $isApified = false;

    /** @var Monitoring */
    private $monitoring;

    public function init()
    {
        $this->checkForRestApiRequest();
        $this->checkDirectorPermissions();
    }

    public function getAuth()
    {
        return $this->Auth();
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

    /**
     * @param int $interval
     * @return $this
     */
    public function setAutorefreshInterval($interval)
    {
        if (! $this->getRequest()->isApiRequest()) {
            parent::setAutorefreshInterval($interval);
        }

        return $this;
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

    /**
     * @param string $permission
     * @return $this
     */
    public function assertPermission($permission)
    {
        parent::assertPermission($permission);
        return $this;
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

            $this->getResponse()->setHeader('Content-Type', 'application/json', true);
            $this->_helper->layout()->disableLayout();
            $this->_helper->viewRenderer->setNoRender(true);

            echo '{ "objects": [' . "\n";
            $objects = array();
            Db\Cache\PrefetchCache::initialize($this->db());

            $out = '';
            $cnt = 0;
            foreach ($dummy::prefetchAll($this->db) as $object) {
                // $objects[] = $object->toPlainObject(false, true);
                // continue;
                $out .= json_encode($object->toPlainObject(false, true), JSON_PRETTY_PRINT) . "\n";
                $cnt++;
                if ($cnt > 50) {
                    echo $out;
                    flush();
                    $cnt = 0;
                    $out = '';
                }
            }

            if ($cnt > 0) {
                echo $out;
            }

            echo "] }\n";
            Benchmark::measure('All done');
            // $this->sendJson((object) array('objects' => $objects));
            echo Benchmark::dump();
            return;
        }

        $this->view->table = $this->applyPaginationLimits($table);
        $this->provideQuickSearch();
    }

    public function postDispatch()
    {
        if ($this->view->content || $this->view->controls) {
            $viewRenderer = new SimpleViewRenderer();
            $viewRenderer->replaceZendViewRenderer();
            $this->view = $viewRenderer->view;
        } else {
            $viewRenderer = null;
        }

        if ($this->getRequest()->isApiRequest()) {
            $this->_helper->layout()->disableLayout();
            if ($viewRenderer) {
                $viewRenderer->disable();
            } else {
                $this->_helper->viewRenderer->setNoRender(true);
            }
        }

        parent::postDispatch(); // TODO: Change the autogenerated stub
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
