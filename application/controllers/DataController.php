<?php

namespace Icinga\Module\Director\Controllers;

use Icinga\Module\Director\Forms\DirectorDatalistEntryForm;
use Icinga\Module\Director\Forms\DirectorDatalistForm;
use Icinga\Module\Director\Objects\DirectorDatalist;
use Icinga\Module\Director\Web\Controller\ActionController;
use Icinga\Module\Director\Web\Table\CustomvarTable;
use Icinga\Module\Director\Web\Table\DatafieldTable;
use Icinga\Module\Director\Web\Table\DatalistEntryTable;
use Icinga\Module\Director\Web\Table\DatalistTable;
use Icinga\Module\Director\Web\Tabs\DataTabs;
use ipl\Html\Link;

class DataController extends ActionController
{
    public function listsAction()
    {
        $this->addTitle($this->translate('Data lists'));
        $this->actions()->add(Link::create(
            $this->translate('Add'),
            'director/data/list',
            null,
            [
                'class' => 'icon-plus',
                'data-base-target' => '_next'
            ]
        ));

        $this->tabs(new DataTabs())->activate('datalist');
        (new DatalistTable($this->db()))->renderTo($this);
    }

    public function listAction()
    {
        $form = DirectorDatalistForm::load()
            ->setSuccessUrl('director/data/lists')
            ->setDb($this->db());
        $this->content()->add($form);

        if ($id = $this->url()->shift('id')) {
            $form->loadObject($id);
            $this->addTitle(
                $this->translate('Data List: %s'),
                $form->getObject()->list_name
            );

            $this->actions()->add(Link::create(
                $this->translate('Entries'),
                'director/data/listentry',
                ['list_id' => $id],
                ['class' => 'icon-doc-text']
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

    public function fieldsAction()
    {
        $this->tabs(new DataTabs())->activate('datafield');
        $this->addTitle($this->translate('Data Fields'));
        $this->actions()->add(Link::create(
            $this->translate('Add'),
            'director/datafield/add',
            null,
            ['class' => 'icon-plus']
        ));

        (new DatafieldTable($this->db()))->renderTo($this);
    }

    public function varsAction()
    {
        $this->tabs(new DataTabs())->activate('customvars');
        $this->addTitle($this->translate('Custom Vars - Overview'));
        (new CustomvarTable($this->db()))->renderTo($this);
    }

    public function listentryAction()
    {
        $url = $this->url();
        $entryName = $url->shift('entry_name');
        $list = DirectorDatalist::load($url->shift('list_id'), $this->db());
        $listId = $list->id;
        $title = $title = $this->translate('List Entries') . ': ' . $list->list_name;
        $this->addTitle($title);

        /** @var DirectorDatalistEntryForm $form */
        $form = DirectorDatalistEntryForm::load()
            ->setSuccessUrl('director/data/listentry?list_id=' . $listId)
            ->setDb($this->db())
            ->setList($list);

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
        $this->content()->add([$form, $table]);
    }
}
