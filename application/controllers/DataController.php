<?php

namespace Icinga\Module\Director\Controllers;

use Icinga\Module\Director\Objects\DirectorDatalist;
use Icinga\Module\Director\Web\Controller\ActionController;

class DataController extends ActionController
{
    public function listsAction()
    {
        $this->view->addLink = $this->view->qlink(
            $this->translate('Add list'),
            'director/data/list',
            null,
            array('class' => 'icon-plus')
        );

        $this->setDataTabs()->activate('datalist');
        $this->view->title = $this->translate('Data lists');
        $this->prepareAndRenderTable('datalist');
    }

    public function listAction()
    {
        $this->view->stayHere = true;

        $form = $this->view->form = $this->loadForm('directorDatalist')
            ->setSuccessUrl('director/data/lists')
            ->setDb($this->db());

        if ($id = $this->getRequest()->getUrl()->shift('id')) {
            $form->loadObject($id);
            $this->view->title = sprintf(
                $this->translate('Data list: %s'),
                $form->getObject()->list_name
            );

            $this->view->addLink = $this->view->qlink(
                $this->translate('back'),
                'director/data/list',
                null,
                array('class' => 'icon-left-big')
            );

            $this->view->addLink .= $this->view->qlink(
                $this->translate('Entries'),
                'director/data/listentry',
                array('list_id' => $id),
                array(
                    'class'            => 'icon-doc-text',
                    'data-base-target' => '_next'
                )
            );

            $this->getTabs()->add('editlist', array(
                'url'       => 'director/data/list' . '?id=' . $id,
                'label'     => $this->translate('Edit list'),
            ))->add('entries', array(
                'url'       => 'director/data/listentry' . '?list_id=' . $id,
                'label'     => $this->translate('List entries'),
            ))->activate('editlist');

        } else {
            $this->view->title = $this->translate('Add data list');

            $this->getTabs()->add('addlist', array(
                'url'       => 'director/data/list',
                'label'     => $this->view->title,
            ))->activate('addlist');
        }

        $form->handleRequest();
        $this->setViewScript('object/form');
    }

    public function indexAction()
    {
        $edit = false;

        if ($id = $this->params->get('id')) {
            $edit = true;
        }

        if ($edit) {
            $this->view->title = $this->translate('Edit list');
            $this->getTabs()->add('editlist', array(
                'url'       => 'director/datalist/edit' . '?id=' . $id,
                'label'     => $this->view->title,
            ))->add('entries', array(
                'url'       => 'director/data/listentry' . '?list_id=' . $id,
                'label'     => $this->translate('List entries'),
            ))->activate('editlist');
        } else {
            $this->view->title = $this->translate('Add list');
            $this->getTabs()->add('addlist', array(
                'url'       => 'director/datalist/add',
                'label'     => $this->view->title,
            ))->activate('addlist');
        }

        $form = $this->view->form = $this->loadForm('directorDatalist')
            ->setSuccessUrl('director/data/lists')
            ->setDb($this->db());

        if ($edit) {
            $form->loadObject($id);
        }

        $form->handleRequest();

        $this->render('object/form', null, true);
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
        $this->view->stayHere = true;

        $url = $this->getRequest()->getUrl();
        $entryName = $url->shift('entry_name');
        $list = DirectorDatalist::load($url->shift('list_id'), $this->db());
        $listId = $list->id;

        $form = $this->view->form = $this->loadForm('directorDatalistentry')
            ->setSuccessUrl('director/data/listentry')
            ->setList($list)
            ->setDb($this->db());

        if ($entryName) {
            $form->loadObject(array(
                'list_id'    => $listId,
                'entry_name' => $entryName
            ));
            $this->view->addLink = $this->view->qlink(
                $this->translate('back'),
                'director/data/listentry' . '?list_id=' . $listId,
                null,
                array('class' => 'icon-left-big')
            );
        }

        $form->handleRequest();


        $this->view->title = $this->translate('List entries')
            . ': ' . $list->list_name;
        $this->getTabs()->add('editlist', array(
            'url'       => 'director/data/list' . '?id=' . $listId,
            'label'     => $this->translate('Edit list'),
        ))->add('datalistentry', array(
            'url'       => 'director/data/listentry' . '?list_id=' . $listId,
            'label'     => $this->view->title,
        ))->activate('datalistentry');

        $this->prepareTable('datalistEntry')->setList($list);
        $this->setViewScript('objects/table');
    }

    protected function prepareTable($name)
    {
        $table = $this->loadTable($name)->setConnection($this->db());
        $this->view->filterEditor = $table->getFilterEditor($this->getRequest());
        $this->view->table = $this->applyPaginationLimits($table);
        return $table;
    }

    protected function prepareAndRenderTable($name)
    {
        $this->prepareTable($name);
        $this->setViewScript('objects/table');
    }
}
