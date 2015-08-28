<?php

use Icinga\Module\Director\Web\Controller\ActionController;
use Icinga\Module\Director\Web\Hook\ImportSourceHook;
use Icinga\Module\Director\Util;
use Icinga\Module\Director\Objects\ImportSource;
use Icinga\Module\Director\Import\Import;
use Icinga\Exception\InvalidPropertyException;
use Icinga\Web\Notification;

class Director_ImportrunController extends ActionController
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
        $this->view->filterEditor = $table->getFilterEditor($this->getRequest());
        $this->render('list/table', null, true);
    }
}
