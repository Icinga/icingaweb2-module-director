<?php

namespace Icinga\Module\Director\Controllers;

use Icinga\Module\Director\Web\Controller\ActionController;
use Exception;

class ListController extends ActionController
{
    public function importsourceAction()
    {
        $this->setAutoRefreshInterval(10);

        $this->view->addLink = $this->view->qlink(
            $this->translate('Add import source'),
            'director/importsource/add',
            null,
            array('class' => 'icon-plus')
        );

        $this->setImportTabs()->activate('importsource');
        $this->view->title = $this->translate('Import source');
        $this->prepareAndRenderTable('importsource');
    }

    public function syncruleAction()
    {
        $this->setAutoRefreshInterval(10);

        $this->view->addLink = $this->view->qlink(
            $this->translate('Add sync rule'),
            'director/syncrule/add',
            null,
            array('class' => 'icon-plus')
        );

        $this->setImportTabs()->activate('syncrule');
        $this->view->title = $this->translate('Sync rule');
        $this->view->table = $this->loadTable('syncrule')->setConnection($this->db());
        $this->render('table');
    }
}
