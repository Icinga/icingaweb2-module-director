<?php

namespace Icinga\Module\Director\Controllers;

use Icinga\Module\Director\DirectorObject\Automation\ImportExport;
use Icinga\Module\Director\Web\Table\ImportsourceTable;
use Icinga\Module\Director\Web\Controller\ActionController;
use Icinga\Module\Director\Web\Tabs\ImportTabs;

class ImportsourcesController extends ActionController
{
    protected $isApified = true;

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
     * @throws \Icinga\Exception\ConfigurationError
     * @throws \Icinga\Exception\Http\HttpNotFoundException
     */
    public function indexAction()
    {
        if ($this->getRequest()->isApiRequest()) {
            switch (strtolower($this->getRequest()->getMethod())) {
                case 'get':
                    $this->sendExport();
                    break;
                case 'post':
                    $this->acceptImport($this->getRequest()->getRawBody());
                    break;
                // TODO: put / replace all?
                default:
                    $this->sendUnsupportedMethod();
            }

            return;
        }

        $this->addTitle($this->translate('Import source'))
            ->setAutoRefreshInterval(10)
            ->addAddLink(
                $this->translate('Add a new Import Source'),
                'director/importsource/add'
            )->tabs(new ImportTabs())->activate('importsource');

        (new ImportsourceTable($this->db()))->renderTo($this);
    }

    /**
     * @param $raw
     * @throws \Icinga\Exception\ConfigurationError
     */
    protected function acceptImport(&$raw)
    {
        (new ImportExport($this->db()))->unserializeImportSources(json_decode($raw));
    }

    /**
     * @throws \Icinga\Exception\ConfigurationError
     */
    protected function sendExport()
    {
        $this->sendJson(
            $this->getResponse(),
            (new ImportExport($this->db()))->serializeAllImportSources()
        );
    }
}
