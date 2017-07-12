<?php

namespace Icinga\Module\Director\Controllers;

use Icinga\Module\Director\Forms\DirectorDatalistentryForm;
use Icinga\Module\Director\Forms\DirectorDatalistForm;
use Icinga\Module\Director\Objects\DirectorDatalist;
use Icinga\Module\Director\Web\Controller\ActionController;
use Icinga\Module\Director\Web\Table\DatafieldTable;
use Icinga\Module\Director\Web\Table\DatalistEntryTable;
use Icinga\Module\Director\Web\Table\DatalistTable;
use ipl\Html\Link;

class DataController extends ActionController
{
    public function listsAction()
    {
        $this->actions()->add(Link::create(
            $this->translate('Add'),
            'director/data/list',
            null,
            ['class' => 'icon-plus']
        ));

        $this->setDataTabs()->activate('datalist');
        $this->addTitle($this->translate('Data lists'));
        $table = new DatalistTable($this->db());
        $table->renderTo($this);
//        $this->prepareAndRenderTable('datalist');
//        $this->provideFilterEditorForTable($this->view->table);
    }

    public function listAction()
    {
        // TODO: check this
        // $this->view->stayHere = true;

        $form = DirectorDatalistForm::load()
            ->setSuccessUrl('director/data/lists')
            ->setDb($this->db());
        $this->content()->add($form);

        if ($id = $this->url()->shift('id')) {
            $form->loadObject($id);
            $this->addTitle(
                $this->translate('Data list: %s'),
                $form->getObject()->list_name
            );

            $this->actions()->add(Link::create(
                $this->translate('back'),
                'director/data/list',
                null,
                ['class' => 'icon-left-big']
            ))->add(Link::create(
                $this->translate('Entries'),
                'director/data/listentry',
                ['list_id' => $id],
                [
                    'class'            => 'icon-doc-text',
                    'data-base-target' => '_next'
                ]
            ));

            $this->tabs()->add('editlist', array(
                'url'       => 'director/data/list' . '?id=' . $id,
                'label'     => $this->translate('Edit list'),
            ))->add('entries', array(
                'url'       => 'director/data/listentry' . '?list_id=' . $id,
                'label'     => $this->translate('List entries'),
            ))->activate('editlist');
        } else {
            $this->addTitle($title = $this->translate('Add'));

            $this->tabs()->add('addlist', array(
                'url'       => 'director/data/list',
                'label'     => $title,
            ))->activate('addlist');
        }

        $form->handleRequest();
    }

    public function indexAction()
    {
        $edit = false;

        if ($id = $this->params->get('id')) {
            $edit = true;
        }

        if ($edit) {
            $this->addTitle($title = $this->translate('Edit list'));

            $this->getTabs()->add('editlist', array(
                'url'       => 'director/datalist/edit' . '?id=' . $id,
                'label'     => $title,
            ))->add('entries', array(
                'url'       => 'director/data/listentry' . '?list_id=' . $id,
                'label'     => $this->translate('List entries'),
            ))->activate('editlist');
        } else {
            $this->addTitle($title = $this->translate('Add list'));
            $this->getTabs()->add('addlist', array(
                'url'       => 'director/datalist/add',
                'label'     => $title,
            ))->activate('addlist');
        }

        $form = DirectorDatalistForm::load()
            ->setSuccessUrl('director/data/lists')
            ->setDb($this->db());

        if ($edit) {
            $form->loadObject($id);
        }

        $form->handleRequest();
    }


    public function fieldsAction()
    {
        $this->actions()->add(Link::create(
            $this->translate('Add'),
            'director/datafield/add',
            null,
            ['class' => 'icon-plus']
        ));

        $this->setDataTabs()->activate('datafield');
        $this->addTitle($this->translate('Data fields'));
        $this->content()->add(new DatafieldTable($this->db()));
        // $this->prepareAndRenderTable('datafield');
        // $this->provideFilterEditorForTable($this->view->table);
    }

    public function listentryAction()
    {
        // $this->view->stayHere = true;

        $url = $this->url();
        $entryName = $url->shift('entry_name');
        $list = DirectorDatalist::load($url->shift('list_id'), $this->db());
        $listId = $list->id;

        $form = DirectorDatalistentryForm::load()
            ->setSuccessUrl('director/data/listentry?list_id=' . $listId)
            ->setList($list)
            ->setDb($this->db());

        if ($entryName) {
            $form->loadObject([
                'list_id'    => $listId,
                'entry_name' => $entryName
            ]);
            $this->actions()->add(Link::create(
                $this->translate('back'),
                'director/data/listentry',
                ['list_id' => $listId],
                ['class' => 'icon-left-big']
            ));
        }
        $form->handleRequest();

        $this->content()->add($form);

        $this->addTitle($title = $this->translate('List entries')
            . ': ' . $list->list_name);
        $this->tabs()->add('editlist', [
            'url'       => 'director/data/list' . '?id=' . $listId,
            'label'     => $this->translate('Edit list'),
        ])->add('datalistentry', [
            'url'       => 'director/data/listentry' . '?list_id=' . $listId,
            'label'     => $title,
        ])->activate('datalistentry');

        $table = new DatalistEntryTable($this->db());
        $table->attributes()->set('data-base-target', '_self');
        $table->setList($list);
        $this->content()->add($table);
    }

    protected function setDataTabs()
    {
        return $this->tabs()->add('datafield', [
            'label' => $this->translate('Data fields'),
            'url'   => 'director/data/fields'
        ])->add('datalist', [
            'label' => $this->translate('Data lists'),
            'url'   => 'director/data/lists'
        ]);
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
