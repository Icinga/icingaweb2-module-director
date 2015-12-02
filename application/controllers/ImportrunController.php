<?php

namespace Icinga\Module\Director\Controllers;

use Icinga\Module\Director\Web\Controller\ActionController;

class ImportrunController extends ActionController
{
    public function indexAction()
    {
        $this->view->title = $this->translate('Import run');
        $table = $this->loadTable('importedrows')
            ->setConnection($this->db())
            ->setChecksum(
                $this->db()->getImportrunRowsetChecksum($this->params->get('id'))
            );
        $this->view->table = $this->applyPaginationLimits($table);
        //$this->view->table->enforceFilter('id', $this->params->shift('id'));
        // $this->view->filterEditor = $table->getFilterEditor($this->getRequest());
        $this->render('list/table', null, true);
    }
}
