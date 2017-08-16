<?php

namespace Icinga\Module\Director\Controllers;

use Icinga\Module\Director\Web\Table\ImportsourceTable;
use Icinga\Module\Director\Web\Controller\ActionController;
use Icinga\Module\Director\Web\Tabs\ImportTabs;

class ImportsourcesController extends ActionController
{
    public function indexAction()
    {
        $this->addTitle($this->translate('Import source'))
            ->setAutoRefreshInterval(10)
            ->addAddLink(
                $this->translate('Add a new Import Source'),
                'director/importsource/add'
            )->tabs(new ImportTabs())->activate('importsource');

        (new ImportsourceTable($this->db()))->renderTo($this);
    }
}
