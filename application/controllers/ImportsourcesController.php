<?php

namespace Icinga\Module\Director\Controllers;

use Icinga\Module\Director\DirectorObject\Automation\ImportExport;
use Icinga\Module\Director\Web\Table\ImportsourceTable;
use Icinga\Module\Director\Web\Controller\ActionController;
use Icinga\Module\Director\Web\Tabs\ImportTabs;

class ImportsourcesController extends ActionController
{
    protected $isApified = true;

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
            ->setAutorefreshInterval(10)
            ->addAddLink(
                $this->translate('Add a new Import Source'),
                'director/importsource/add'
            )->tabs(new ImportTabs())->activate('importsource');

        (new ImportsourceTable($this->db()))->renderTo($this);
    }

    /**
     * @param $raw
     */
    protected function acceptImport($raw)
    {
        (new ImportExport($this->db()))->unserializeImportSources(json_decode($raw));
    }

    protected function sendExport()
    {
        $this->sendJson(
            $this->getResponse(),
            (new ImportExport($this->db()))->serializeAllImportSources()
        );
    }
}
