<?php

namespace Icinga\Module\Director\Controllers;

use Icinga\Module\Director\Web\Controller\ActionController;

class DataController extends ActionController
{
    public function listsAction()
    {
        $this->view->addLink = $this->view->qlink(
            $this->translate('Add list'),
            'director/datalist/add',
            null,
            array('class' => 'icon-plus')
        );

        $this->setDataTabs()->activate('datalist');
        $this->view->title = $this->translate('Data lists');
        $this->prepareAndRenderTable('datalist');
    }

    public function fieldsAction()
    {
        $this->view->addLink = $this->view->qlink(
            $this->translate('Add field'),
            'director/datafield/add',
            null,
            array('class' => 'icon-plus')

        );

        $this->setDataTabs()->activate('datafield');
        $this->view->title = $this->translate('Data fields');
        $this->prepareAndRenderTable('datafield');
    }

    public function listentryAction()
    {
        $listId = $this->params->get('list_id');
        $this->view->lastId = $listId;

        $this->view->addLink = $this->view->qlink(
            $this->translate('Add entry'),
            'director/datalistentry/add' . '?list_id=' . $listId,
            null,
            array('class' => 'icon-plus')

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

    protected function prepareTable($name)
    {
        $table = $this->loadTable($name)->setConnection($this->db());
        $this->view->filterEditor = $table->getFilterEditor($this->getRequest());
        $this->view->table = $this->applyPaginationLimits($table);
        return $this;
    }

    protected function prepareAndRenderTable($name)
    {
        $this->prepareTable($name)->render('objects/table', null, 'objects');
    }
}
