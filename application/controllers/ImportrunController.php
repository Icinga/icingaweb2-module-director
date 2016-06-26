<?php

namespace Icinga\Module\Director\Controllers;

use Icinga\Module\Director\Web\Controller\ActionController;

class ImportrunController extends ActionController
{
    public function indexAction()
    {
        $id = $this->getRequest()->getUrl()->getParams()->shift('id');
        $this->view->title = $this->translate('Import run');
        $table = $this
            ->loadTable('importedrows')
            ->setConnection($this->db())
            ->setChecksum(
                $this->db()->getImportrunRowsetChecksum($id)
            );

        $this->view->table = $this->applyPaginationLimits($table);
        $this->view->filterEditor = $table->getFilterEditor($this->getRequest());
        $this->setViewScript('list/table');
    }
}
