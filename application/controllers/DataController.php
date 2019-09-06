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
use gipfl\IcingaWeb2\Link;

class DataController extends ActionController
{
    public function listsAction()
    {
        $this->addTitle($this->translate('Data lists'));
        $this->actions()->add(
            Link::create($this->translate('Add'), 'director/data/list', null, [
                'class' => 'icon-plus',
                'data-base-target' => '_next'
            ])
        );

        $this->tabs(new DataTabs())->activate('datalist');
        (new DatalistTable($this->db()))->renderTo($this);
    }

    /**
     * @throws \Icinga\Exception\MissingParameterException
     * @throws \Icinga\Exception\NotFoundError
     */
    public function listAction()
    {
        $form = DirectorDatalistForm::load()
            ->setSuccessUrl('director/data/lists')
            ->setDb($this->db());

        if ($name = $this->params->get('name')) {
            $list = $this->requireList('name');
            $form->setObject($list);
            $this->addListActions($list);
            $this->addTitle(
                $this->translate('Data List: %s'),
                $list->get('list_name')
            )->addListTabs($name, 'list');
        } else {
            $this
                ->addTitle($this->translate('Add a new Data List'))
                ->addSingleTab($this->translate('Data List'));
        }

        $this->content()->add($form->handleRequest());
    }

    public function fieldsAction()
    {
        $this->setAutorefreshInterval(10);
        $this->tabs(new DataTabs())->activate('datafield');
        $this->addTitle($this->translate('Data Fields'));
        $this->actions()->add(Link::create(
            $this->translate('Add'),
            'director/datafield/add',
            null,
            [
                'class' => 'icon-plus',
                'data-base-target' => '_next',
            ]
        ));

        (new DatafieldTable($this->db()))->renderTo($this);
    }

    public function varsAction()
    {
        $this->tabs(new DataTabs())->activate('customvars');
        $this->addTitle($this->translate('Custom Vars - Overview'));
        (new CustomvarTable($this->db()))->renderTo($this);
    }

    /**
     * @throws \Icinga\Exception\MissingParameterException
     * @throws \Icinga\Exception\NotFoundError
     */
    public function listentryAction()
    {
        $entryName = $this->params->get('entry_name');
        $list = $this->requireList('list');
        $this->addListActions($list);
        $listId = $list->get('id');
        $listName = $list->get('list_name');
        $title = $title = $this->translate('List Entries') . ': ' . $listName;
        $this->addTitle($title);

        $form = DirectorDatalistEntryForm::load()
            ->setSuccessUrl('director/data/listentry', ['list' => $listName])
            ->setList($list);

        if (null !== $entryName) {
            $form->loadObject([
                'list_id'    => $listId,
                'entry_name' => $entryName
            ]);
            $this->actions()->add(Link::create(
                $this->translate('back'),
                'director/data/listentry',
                ['list' => $listName],
                ['class' => 'icon-left-big']
            ));
        }
        $form->handleRequest();

        $this->addListTabs($listName, 'entries');

        $table = new DatalistEntryTable($this->db());
        $table->getAttributes()->set('data-base-target', '_self');
        $table->setList($list);
        $this->content()->add([$form, $table]);
    }

    protected function addListActions(DirectorDatalist $list)
    {
        $this->actions()->add(
            Link::create(
                $this->translate('Add to Basket'),
                'director/basket/add',
                [
                    'type'  => 'DataList',
                    'names' => $list->getUniqueIdentifier()
                ],
                ['class' => 'icon-tag']
            )
        );
    }

    /**
     * @param $paramName
     * @return DirectorDatalist
     * @throws \Icinga\Exception\MissingParameterException
     * @throws \Icinga\Exception\NotFoundError
     */
    protected function requireList($paramName)
    {
        return DirectorDatalist::load($this->params->getRequired($paramName), $this->db());
    }

    protected function addListTabs($name, $activate)
    {
        $this->tabs()->add('list', [
            'url'       => 'director/data/list',
            'urlParams' => ['name' => $name],
            'label'     => $this->translate('Edit list'),
        ])->add('entries', [
            'url'       => 'director/data/listentry',
            'urlParams' => ['list' => $name],
            'label'     => $this->translate('List entries'),
        ])->activate($activate);

        return $this;
    }
}
