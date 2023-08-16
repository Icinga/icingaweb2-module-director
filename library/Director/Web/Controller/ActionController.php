<?php

namespace Icinga\Module\Director\Web\Controller;

use gipfl\Translation\StaticTranslator;
use Icinga\Application\Benchmark;
use Icinga\Data\Paginatable;
use Icinga\Exception\NotFoundError;
use Icinga\Exception\ProgrammingError;
use Icinga\Module\Director\Integration\MonitoringModule\Monitoring;
use Icinga\Module\Director\Web\Controller\Extension\CoreApi;
use Icinga\Module\Director\Web\Controller\Extension\DirectorDb;
use Icinga\Module\Director\Web\Controller\Extension\RestApi;
use Icinga\Module\Director\Web\Window;
use Icinga\Security\SecurityException;
use Icinga\Web\Controller;
use Icinga\Web\UrlParams;
use InvalidArgumentException;
use gipfl\IcingaWeb2\Translator;
use gipfl\IcingaWeb2\Link;
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
        if (! $this->getRequest()->isApiRequest()
            && $this->Config()->get('frontend', 'disabled', 'no') === 'yes'
        ) {
            throw new NotFoundError('Not found');
        }
        $this->initializeTranslator();
        Benchmark::measure('Director base Controller init()');
        $this->checkForRestApiRequest();
        $this->checkDirectorPermissions();
        $this->checkSpecialDirectorPermissions();
    }

    protected function initializeTranslator()
    {
        StaticTranslator::set(new Translator('director'));
    }

    public function getAuth()
    {
        return $this->Auth();
    }

    /**
     * @codingStandardsIgnoreStart
     * @return Window
     */
    public function Window()
    {
        // @codingStandardsIgnoreEnd
        if ($this->window === null) {
            $this->window = new Window(
                $this->_request->getHeader('X-Icinga-WindowId', Window::UNDEFINED)
            );
        }
        return $this->window;
    }

    /**
     * @throws SecurityException
     */
    protected function checkDirectorPermissions()
    {
        $this->hasPermission('director/' . $this->getPluralBaseType());
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

    protected function sendUnsupportedMethod()
    {
        $method = strtoupper($this->getRequest()->getMethod()) ;
        $response = $this->getResponse();
        $this->sendJsonError($response, sprintf(
            'Method %s is not supported',
            $method
        ), 422);  // TODO: check response code
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
            // Hint -> $this->view->compact is the only way since v2.8.0
            if ($this->view->compact || $this->getOriginalUrl()->getParam('view') === 'compact') {
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
            $this->monitoring = new Monitoring($this->Auth());
        }

        return $this->monitoring;
    }
            /**
     * @return string
     */
    protected function getBaseType()
    {
        $type = $this->getType();
        if (substr($type, -5) === 'Group') {
            return substr($type, 0, -5);
        } else {
            return $type;
        }
    }

    protected function getBaseObjectUrl()
    {
        return $this->getType();
    }

    /**
     * @return string
     */
    protected function getType()
    {
        // Strip final 's' and upcase an eventual 'group'
        return preg_replace(
            array('/group$/', '/period$/', '/argument$/', '/apiuser$/', '/dependencie$/', '/set$/'),
            array('Group', 'Period', 'Argument', 'ApiUser', 'dependency', 'Set'),
            str_replace(
                'template',
                '',
                substr($this->getRequest()->getControllerName(), 0, -1)
            )
        );
    }

    /**
     * @return string
     */
    protected function getPluralType()
    {
        return preg_replace('/cys$/', 'cies', $this->getType() . 's');
    }

    /**
     * @return string
     */
    protected function getPluralBaseType()
    {
        return preg_replace('/cys$/', 'cies', $this->getBaseType() . 's');
    }
}
