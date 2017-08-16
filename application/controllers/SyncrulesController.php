<?php

namespace Icinga\Module\Director\Controllers;

use Icinga\Module\Director\Web\Table\SyncruleTable;
use Icinga\Module\Director\Web\Controller\ActionController;
use Icinga\Module\Director\Web\Tabs\ImportTabs;

class SyncrulesController extends ActionController
{
    public function indexAction()
    {
        $this->addTitle($this->translate('Sync rule'))
            ->setAutoRefreshInterval(10)
            ->addAddLink(
                $this->translate('Add a new Sync Rule'),
                'director/syncrule/add'
            )->tabs(new ImportTabs())->activate('syncrule');

        (new SyncruleTable($this->db()))->renderTo($this);
    }
}
