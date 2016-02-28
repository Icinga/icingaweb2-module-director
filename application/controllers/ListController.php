<?php

namespace Icinga\Module\Director\Controllers;

use Icinga\Module\Director\Web\Controller\ActionController;
use Exception;

class ListController extends ActionController
{
    public function datalistAction()
    {
        $this->view->addLink = $this->view->icon('plus')
            . ' '
            . $this->view->qlink(
                $this->translate('Add list'),
                'director/datalist/add'
            );

        $this->setConfigTabs()->activate('datalist');
        $this->view->title = $this->translate('Data lists');
        $this->prepareAndRenderTable('datalist');
    }

    public function datalistentryAction()
    {
        $listId = $this->params->get('list_id');
        $this->view->lastId = $listId;

        $this->view->addLink = $this->view->icon('plus')
            . ' '
            . $this->view->qlink(
                $this->translate('Add entry'),
                'director/datalistentry/add' . '?list_id=' . $listId
            );

        $this->view->title = $this->translate('List entries');
        $this->getTabs()->add('editlist', array(
            'url'       => 'director/datalist/edit' . '?id=' . $listId,
            'label'     => $this->translate('Edit list'),
        ))->add('datalistentry', array(
            'url'       => 'director/datalistentry' . '?list_id=' . $listId,
            'label'     => $this->view->title,
        ))->activate('datalistentry');

        $this->prepareAndRenderTable('datalistEntry');
    }

    public function datafieldAction()
    {
        $this->view->addLink = $this->view->icon('plus')
            . ' '
            . $this->view->qlink(
                $this->translate('Add field'),
                'director/datafield/add'
            );

        $this->setConfigTabs()->activate('datafield');
        $this->view->title = $this->translate('Data fields');
        $this->prepareAndRenderTable('datafield');
    }

    public function importsourceAction()
    {
        $this->view->addLink = $this->view->icon('plus')
            . ' '
            . $this->view->qlink(
                $this->translate('Add import source'),
                'director/importsource/add'
            );

        $this->setImportTabs()->activate('importsource');
        $this->view->title = $this->translate('Import source');
        $this->prepareAndRenderTable('importsource');
    }

    public function importrunAction()
    {
        $this->setImportTabs()->activate('importrun');
        $this->view->title = $this->translate('Import runs');
        $this->view->stats = $this->db()->fetchImportStatistics();
        $this->prepareAndRenderTable('importrun');
    }

    public function syncruleAction()
    {
        $this->view->addLink = $this->view->icon('plus')
            . ' '
            . $this->view->qlink(
                $this->translate('Add sync rule'),
                'director/syncrule/add'
            );

        $this->setImportTabs()->activate('syncrule');
        $this->view->title = $this->translate('Sync rule');
        $this->view->table = $this->loadTable('syncrule')->setConnection($this->db());
        $this->render('table');
    }
}
