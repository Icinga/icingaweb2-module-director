<?php

namespace Icinga\Module\Director\Controllers;

use Icinga\Module\Director\Objects\ImportRun;
use Icinga\Module\Director\Web\Controller\ActionController;

class ImportrunController extends ActionController
{
    public function indexAction()
    {
        $db = $this->db();
        $id = $this->getRequest()->getUrl()->getParams()->shift('id');
        $importRun = ImportRun::load($id, $db);
        $url = clone($this->getRequest()->getUrl());
        $chosenColumns = $this->getRequest()->getUrl()->shift('chosenColumns');

        $this->view->title = $this->translate('Import run');
        $this->getTabs()->add('importrun', array(
            'label' => $this->view->title,
            'url'   => $url
        ))->activate('importrun');

        $table = $this
            ->loadTable('importedrows')
            ->setConnection($db)
            ->setImportRun($importRun);

        if ($chosenColumns) {
            $table->setColumns(preg_split('/,/', $chosenColumns, -1, PREG_SPLIT_NO_EMPTY));
        }

        $this->view->table = $this->applyPaginationLimits($table);
        $this->view->filterEditor = $table->getFilterEditor($this->getRequest());
    }
}
