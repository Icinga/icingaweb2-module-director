<?php

namespace Icinga\Module\Director\Web\Controller;

use Icinga\Application\Benchmark;
use Icinga\Data\Paginatable;
use Icinga\Module\Director\Monitoring;
use Icinga\Module\Director\Web\Controller\Extension\CoreApi;
use Icinga\Module\Director\Web\Controller\Extension\DirectorDb;
use Icinga\Module\Director\Web\Controller\Extension\RestApi;
use Icinga\Module\Director\Web\Form\FormLoader;
use Icinga\Module\Director\Web\Form\QuickForm;
use Icinga\Module\Director\Web\Table\QuickTable;
use Icinga\Module\Director\Web\Table\TableLoader;
use Icinga\Security\SecurityException;
use Icinga\Web\Controller;
use Icinga\Web\UrlParams;
use Icinga\Web\Widget;
use ipl\Compat\Translator;
use ipl\Html\Link;
use ipl\Translation\TranslationHelper;
use ipl\Web\Widget\ControlsAndContent;
use ipl\Web\Controller\Extension\ControlsAndContentHelper;
use ipl\Zf1\SimpleViewRenderer;

abstract class ActionController extends Controller implements ControlsAndContent
{
    use DirectorDb;
    use CoreApi;
    use RestApi;
    use ControlsAndContentHelper;

    protected $isApified = false;

    /** @var UrlParams Hint for IDE, somehow does not work in web */
    protected $params;

    /** @var Monitoring */
    private $monitoring;

    public function init()
    {
        $this->initializeTranslator();
        Benchmark::measure('Director base Controller init()');
        $this->checkForRestApiRequest();
        $this->checkDirectorPermissions();
    }

    protected function initializeTranslator()
    {
        TranslationHelper::setTranslator(new Translator('director'));
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

    protected function addAddLink($title, $url, $urlParams = null, $target = '_next')
    {
        $this->actions()->add(Link::create(
            $this->translate('Add'),
            $url,
            $urlParams,
            [
                'class' => 'icon-plus',
                'title' => $title,
                'data-base-target' => $target
            ]
        ));

        return $this;
    }

    protected function addBackLink($url, $urlParams = null)
    {
        $this->actions()->add(new Link(
            $this->translate('back'),
            $url,
            $urlParams,
            ['class' => 'icon-left-big']
        ));

        return $this;
    }

    /**
     * @param string $name
     *
     * @return QuickForm
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

    /**
     * @param string $permission
     * @return $this
     */
    public function assertPermission($permission)
    {
        parent::assertPermission($permission);
        return $this;
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

    public function postDispatch()
    {
        Benchmark::measure('Director postDispatch');
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
