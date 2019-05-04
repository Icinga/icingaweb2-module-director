<?php

namespace Icinga\Module\Director\Controllers;

use Icinga\Module\Director\Objects\ImportRun;
use Icinga\Module\Director\Web\Controller\ActionController;
use Icinga\Module\Director\Web\Table\ImportedrowsTable;

class ImportrunController extends ActionController
{
    public function indexAction()
    {
        $importRun = ImportRun::load($this->params->getRequired('id'), $this->db());
        $this->addTitle($this->translate('Import run'));
        $this->addSingleTab($this->translate('Import run'));

        $table = ImportedrowsTable::load($importRun);
        if ($chosen = $this->params->get('chosenColumns')) {
            $table->setColumns(preg_split('/,/', $chosen, -1, PREG_SPLIT_NO_EMPTY));
        }

        $table->renderTo($this);
    }
}
