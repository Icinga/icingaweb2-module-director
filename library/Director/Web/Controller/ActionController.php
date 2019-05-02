<?php

namespace Icinga\Module\Director\Web\Controller;

use Icinga\Application\Benchmark;
use Icinga\Data\Paginatable;
use Icinga\Exception\ProgrammingError;
use Icinga\Module\Director\Monitoring;
use Icinga\Module\Director\Web\Controller\Extension\CoreApi;
use Icinga\Module\Director\Web\Controller\Extension\DirectorDb;
use Icinga\Module\Director\Web\Controller\Extension\RestApi;
use Icinga\Security\SecurityException;
use Icinga\Web\Controller;
use Icinga\Web\UrlParams;
use InvalidArgumentException;
use gipfl\IcingaWeb2\Translator;
use gipfl\IcingaWeb2\Link;
use gipfl\Translation\TranslationHelper;
use gipfl\IcingaWeb2\Widget\ControlsAndContent;
use gipfl\IcingaWeb2\Controller\Extension\ControlsAndContentHelper;
use gipfl\IcingaWeb2\Zf1\SimpleViewRenderer;
use GuzzleHttp\Psr7\ServerRequest;
use Psr\Http\Message\ServerRequestInterface;

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

    /**
     * @throws SecurityException
     * @throws \Icinga\Exception\AuthenticationException
     * @throws \Icinga\Exception\NotFoundError
     */
    public function init()
    {
        $this->initializeTranslator();
        Benchmark::measure('Director base Controller init()');
        $this->checkForRestApiRequest();
        $this->checkDirectorPermissions();
        $this->checkSpecialDirectorPermissions();
    }

    protected function initializeTranslator()
    {
        TranslationHelper::setTranslator(new Translator('director'));
    }

    public function getAuth()
    {
        return $this->Auth();
    }

    /**
     * @throws SecurityException
     */
    protected function checkDirectorPermissions()
    {
        $this->assertPermission('director/admin');
    }

    /**
     * @throws SecurityException
     */
    protected function checkSpecialDirectorPermissions()
    {
        if ($this->params->get('format') === 'sql') {
            $this->assertPermission('director/showsql');
        }
    }

    /**
     * Assert that the current user has one of the given permission
     *
     * @param   array $permissions Permission name list
     *
     * @return $this
     * @throws  SecurityException       If the current user lacks the given permission
     */
    protected function assertOneOfPermissions($permissions)
    {
        $auth = $this->Auth();

        foreach ($permissions as $permission) {
            if ($auth->hasPermission($permission)) {
                return $this;
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
            try {
                parent::setAutorefreshInterval($interval);
            } catch (ProgrammingError $e) {
                throw new InvalidArgumentException($e->getMessage());
            }
        }

        return $this;
    }

    /**
     * @return ServerRequestInterface
     */
    protected function getServerRequest()
    {
        return ServerRequest::fromGlobals();
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
     * @param string $permission
     * @return $this
     * @throws SecurityException
     */
    public function assertPermission($permission)
    {
        parent::assertPermission($permission);
        return $this;
    }

    public function postDispatch()
    {
        Benchmark::measure('Director postDispatch');
        if ($this->view->content || $this->view->controls) {
            $viewRenderer = new SimpleViewRenderer();
            $viewRenderer->replaceZendViewRenderer();
            $this->view = $viewRenderer->view;
            if ($this->getOriginalUrl()->getParam('view') === 'compact') {
                if ($this->view->controls) {
                    $this->controls()->getAttributes()->add('style', 'display: none;');
                }
            }
        } else {
            $viewRenderer = null;
        }

        $cType = $this->getResponse()->getHeader('Content-Type', true);
        if ($this->getRequest()->isApiRequest() || ($cType !== null && $cType !== 'text/html')) {
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
