<?php

namespace Icinga\Module\Director\Web\Controller;

use Dompdf\Dompdf;
use Dompdf\Options;
use gipfl\Translation\StaticTranslator;
use Icinga\Application\Benchmark;
use Icinga\Application\Hook;
use Icinga\Application\Hook\PdfexportHook;
use Icinga\Application\Icinga;
use Icinga\Application\Modules\Module;
use Icinga\Data\Paginatable;
use Icinga\Exception\NotFoundError;
use Icinga\Exception\ProgrammingError;
use Icinga\Module\Director\Integration\Icingadb\IcingadbBackend;
use Icinga\Module\Director\Integration\BackendInterface;
use Icinga\Module\Director\Integration\MonitoringModule\Monitoring;
use Icinga\Module\Director\ProvidedHook\Icingadb\IcingadbSupport;
use Icinga\Module\Director\Web\Controller\Extension\CoreApi;
use Icinga\Module\Director\Web\Controller\Extension\DirectorDb;
use Icinga\Module\Director\Web\Controller\Extension\RestApi;
use Icinga\Module\Director\Web\Window;
use Icinga\Security\SecurityException;
use Icinga\Util\Environment;
use Icinga\Web\Controller;
use Icinga\Web\FileCache;
use Icinga\Web\Url;
use Icinga\Web\UrlParams;
use InvalidArgumentException;
use gipfl\IcingaWeb2\Translator;
use gipfl\IcingaWeb2\Link;
use gipfl\IcingaWeb2\Widget\ControlsAndContent;
use gipfl\IcingaWeb2\Controller\Extension\ControlsAndContentHelper;
use gipfl\IcingaWeb2\Zf1\SimpleViewRenderer;
use GuzzleHttp\Psr7\ServerRequest;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;

abstract class ActionController extends Controller implements ControlsAndContent
{
    use DirectorDb;
    use CoreApi;
    use RestApi;
    use ControlsAndContentHelper;

    protected $isApified = false;

    /** @var UrlParams Hint for IDE, somehow does not work in web */
    protected $params;

    /** @var BackendInterface */
    private $backend;

    /**
     * @throws SecurityException
     * @throws \Icinga\Exception\AuthenticationException
     * @throws \Icinga\Exception\NotFoundError
     */
    public function init()
    {
        if (
            ! $this->getRequest()->isApiRequest()
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
                    $this->controls()->getAttributes()->add('class', 'compact');
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

        $req = $this->getRequest();
        if ($req->getParam('error_handler') === null && $req->getParam('format') === 'pdf') {
            // core's pdf export expects a CompatController or a classic Zend view script,
            // neither of which applies here since we already rendered via SimpleViewRenderer
            $this->sendAsPdf();
            $this->shutdownSession();
            exit;
        }

        parent::postDispatch(); // TODO: Change the autogenerated stub
    }

    /**
     * Renders the current action as a pdf and streams it to the client
     *
     * This is a stripped down copy of Icinga\File\Pdf::renderControllerAction().
     * That core method calls $viewRenderer->getResponseSegment() to clear the
     * response body again once it's done, but that's a method our own
     * SimpleViewRenderer doesn't have, it blows up with a fatal error. We
     * don't need that cleanup here anyway, we exit right after this call.
     */
    protected function sendAsPdf()
    {
        Environment::raiseMemoryLimit('512M');
        Environment::raiseExecutionTime(300);

        $viewRenderer = $this->getHelper('viewRenderer');
        $viewRenderer->postDispatch();

        $layoutHelper = $this->getHelper('layout');
        $layout = $layoutHelper->setLayout('pdf');
        $layout->content = $this->getResponse();
        $html = $layout->render();

        $imgDir = Url::fromPath('img');
        $html = preg_replace(
            '~src="' . $imgDir . '/~',
            'src="' . Icinga::app()->getBootstrapDirectory() . '/img/',
            $html
        );

        // core's page number relies on a CSS counter neither dompdf nor a headless
        // browser print engine ever increments here, so it always shows "Page 0"
        // and its border sits right on top of the last row. just drop it for now.
        $html = str_replace('<div class="page-number"></div>', '', $html);

        $request = $this->getRequest();
        $filename = sprintf('%s-%s-%d', $request->getControllerName(), $request->getActionName(), time());

        if (Hook::has('Pdfexport')) {
            PdfexportHook::first()->streamPdfFromHtml($html, $filename);

            return;
        }

        $tmpDir = FileCache::instance()->directory('legacy_pdf');
        if ($tmpDir === false) {
            throw new RuntimeException('Could not create temporary directory for PDF rendering');
        }

        $options = new Options();
        $options->set('defaultPaperSize', 'A4');
        $options->set('fontDir', $tmpDir);
        $options->set('fontCache', $tmpDir);
        $options->set('tempDir', $tmpDir);
        $options->set('chroot', $tmpDir);
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->render();
        $dompdf->stream($filename);
    }

    /**
     * @return BackendInterface
     */
    protected function backend(): BackendInterface
    {
        if ($this->backend === null) {
            if (Module::exists('icingadb') && IcingadbSupport::useIcingaDbAsBackend()) {
                $this->backend = new IcingadbBackend();
            } else {
                $this->backend = new Monitoring($this->getAuth());
            }
        }

        return $this->backend;
    }
}
