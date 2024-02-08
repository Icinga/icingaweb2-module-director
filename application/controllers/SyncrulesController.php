<?php

namespace Icinga\Module\Director\Controllers;

use Icinga\Module\Director\DirectorObject\Automation\ImportExport;
use Icinga\Module\Director\Web\Table\SyncruleTable;
use Icinga\Module\Director\Web\Controller\ActionController;
use Icinga\Module\Director\Web\Tabs\ImportTabs;

class SyncrulesController extends ActionController
{
    protected $isApified = true;

    /**
     * @throws \Icinga\Exception\ConfigurationError
     * @throws \Icinga\Exception\Http\HttpNotFoundException
     */
    public function indexAction()
    {
        if ($this->getRequest()->isApiRequest()) {
            $this->sendExport();
            return;
        }

        $this->addTitle($this->translate('Sync rule'))
            ->setAutorefreshInterval(10)
            ->addAddLink(
                $this->translate('Add a new Sync Rule'),
                'director/syncrule/add'
            )->tabs(new ImportTabs())->activate('syncrule');

        (new SyncruleTable($this->db()))->renderTo($this);
    }

    /**
     * @throws \Icinga\Exception\ConfigurationError
     */
    protected function sendExport()
    {
        $this->sendJson(
            $this->getResponse(),
            (new ImportExport($this->db()))->serializeAllSyncRules()
        );
    }
}
