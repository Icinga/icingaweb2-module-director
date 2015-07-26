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
        $this->view->table = $this->applyPaginationLimits(
            $this->loadTable('importedrows')
                ->setChecksum(
                    $this->db()->getImportrunRowsetChecksum($this->params->get('id'))
                )
                ->setConnection($this->db())
        );
        $this->render('list/table', null, true);
    }
}
