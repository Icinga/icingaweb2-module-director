<?php

namespace Icinga\Module\Director\Controllers;

use gipfl\Web\Widget\Hint;
use Icinga\Exception\NotFoundError;
use Icinga\Module\Director\Forms\DirectorDatalistEntryForm;
use Icinga\Module\Director\Forms\DirectorDatalistForm;
use Icinga\Module\Director\Forms\IcingaServiceDictionaryMemberForm;
use Icinga\Module\Director\Objects\DirectorDatalist;
use Icinga\Module\Director\Objects\IcingaHost;
use Icinga\Module\Director\Objects\IcingaObject;
use Icinga\Module\Director\Objects\IcingaService;
use Icinga\Module\Director\PlainObjectRenderer;
use Icinga\Module\Director\Web\Controller\ActionController;
use Icinga\Module\Director\Web\Form\IcingaObjectFieldLoader;
use Icinga\Module\Director\Web\Table\CustomvarTable;
use Icinga\Module\Director\Web\Table\DatafieldCategoryTable;
use Icinga\Module\Director\Web\Table\DatafieldTable;
use Icinga\Module\Director\Web\Table\DatalistEntryTable;
use Icinga\Module\Director\Web\Table\DatalistTable;
use Icinga\Module\Director\Web\Tabs\DataTabs;
use gipfl\IcingaWeb2\Link;
use InvalidArgumentException;
use ipl\Html\Html;
use ipl\Html\Table;

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

    public function fieldcategoriesAction()
    {
        $this->setAutorefreshInterval(10);
        $this->tabs(new DataTabs())->activate('datafieldcategory');
        $this->addTitle($this->translate('Data Field Categories'));
        $this->actions()->add(Link::create(
            $this->translate('Add'),
            'director/datafieldcategory/add',
            null,
            [
                'class' => 'icon-plus',
                'data-base-target' => '_next',
            ]
        ));

        (new DatafieldCategoryTable($this->db()))->renderTo($this);
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

    public function dictionaryAction()
    {
        $connection = $this->db();
        $this->addSingleTab('Nested Dictionary');
        $varName = $this->params->get('varname');
        $instance = $this->url()->getParam('instance');
        $action = $this->url()->getParam('action');
        $object = $this->requireObject();

        if ($instance || $action) {
            $this->actions()->add(
                Link::create($this->translate('Back'), $this->url()->without(['action', 'instance']), null, [
                    'class' => 'icon-edit'
                ])
            );
        } else {
            $this->actions()->add(
                Link::create($this->translate('Add'), $this->url(), [
                    'action' => 'add'
                ], [
                    'class' => 'icon-edit'
                ])
            );
        }
        $subjects = $this->prepareSubjectsLabel($object, $varName);
        $fieldLoader = new IcingaObjectFieldLoader($object);
        $instances = $this->getCurrentInstances($object, $varName);

        if (empty($instances)) {
            $this->content()->add(Hint::info(sprintf(
                $this->translate('No %s have been created yet'),
                $subjects
            )));
        } else {
            $this->content()->add($this->prepareInstancesTable($instances));
        }

        $field = $this->getFieldByName($fieldLoader, $varName);
        $template = $object::load([
            'object_name' => $field->getSetting('template_name')
        ], $connection);

        $form = new IcingaServiceDictionaryMemberForm();
        $form->setDb($connection);
        if ($instance) {
            $instanceObject = $object::create([
                'imports'    => [$template],
                'object_name' => $instance,
                'vars' => $instances[$instance]
            ], $connection);
            $form->setObject($instanceObject);
        } elseif ($action === 'add') {
            $form->presetImports([$template->getObjectName()]);
        } else {
            return;
        }
        if ($instance) {
            if (! isset($instances[$instance])) {
                throw new NotFoundError("There is no such instance: $instance");
            }
            $subTitle = sprintf($this->translate('Modify instance: %s'), $instance);
        } else {
            $subTitle = $this->translate('Add a new instance');
        }

        $this->content()->add(Html::tag('h2', ['class' => 'dictionary-header'], $subTitle));
        $form->handleRequest($this->getRequest());
        $this->content()->add($form);
        if ($form->succeeded()) {
            $virtualObject = $form->getObject();
            $name = $virtualObject->getObjectName();
            $params = $form->getObject()->getVars();
            $instances[$name] = $params;
            if ($name !== $instance) { // Has been renamed
                unset($instances[$instance]);
            }
            ksort($instances);
            $object->set("vars.$varName", (object)$instances);
            $object->store();
            $this->redirectNow($this->url()->without(['instance', 'action']));
        } elseif ($form->shouldBeDeleted()) {
            unset($instances[$instance]);
            if (empty($instances)) {
                $object->set("vars.$varName", null)->store();
            } else {
                $object->set("vars.$varName", (object)$instances)->store();
            }
            $this->redirectNow($this->url()->without(['instance', 'action']));
        }
    }

    protected function requireObject()
    {
        $connection = $this->db();
        $hostName = $this->params->getRequired('host');
        $serviceName = $this->params->get('service');
        if ($serviceName) {
            $host = IcingaHost::load($hostName, $connection);
            $object = IcingaService::load([
                'host_id'     => $host->get('id'),
                'object_name' => $serviceName,
            ], $connection);
        } else {
            $object = IcingaHost::load($hostName, $connection);
        }

        if (! $object->isObject()) {
            throw new InvalidArgumentException(sprintf(
                'Only single objects allowed, %s is a %s',
                $object->getObjectName(),
                $object->get('object_type')
            ));
        }
        return $object;
    }

    protected function shorten($string, $maxLen)
    {
        if (strlen($string) <= $maxLen) {
            return $string;
        }

        return substr($string, 0, $maxLen) . '...';
    }

    protected function getFieldByName(IcingaObjectFieldLoader $loader, $name)
    {
        foreach ($loader->getFields() as $field) {
            if ($field->get('varname') === $name) {
                return $field;
            }
        }

        throw new InvalidArgumentException("Found no configured field for '$name'");
    }

    /**
     * @param IcingaObject $object
     * @param $varName
     * @return array
     */
    protected function getCurrentInstances(IcingaObject $object, $varName)
    {
        $currentVars = $object->getVars();
        if (isset($currentVars->$varName)) {
            $currentValue = $currentVars->$varName;
        } else {
            $currentValue = (object)[];
        }
        if (is_object($currentValue)) {
            $currentValue = (array)$currentValue;
        } else {
            throw new InvalidArgumentException(sprintf(
                '"%s" is not a valid Dictionary',
                json_encode($currentValue)
            ));
        }
        return $currentValue;
    }

    /**
     * @param array $currentValue
     * @param $subjects
     * @return Hint|Table
     */
    protected function prepareInstancesTable(array $currentValue)
    {
        $table = new Table();
        $table->addAttributes([
            'class' => 'common-table table-row-selectable'
        ]);
        $table->getHeader()->add(
            Table::row([
                $this->translate('Key / Instance'),
                $this->translate('Properties')
            ], ['class' => 'text-align-left'], 'th')
        );
        foreach ($currentValue as $key => $item) {
            $table->add(Table::row([
                Link::create($key, $this->url()->with('instance', $key)),
                str_replace("\n", ' ', $this->shorten(PlainObjectRenderer::render($item), 512))
            ]));
        }

        return $table;
    }

    /**
     * @param IcingaObject $object
     * @param $varName
     * @return string
     */
    protected function prepareSubjectsLabel(IcingaObject $object, $varName)
    {
        if ($object instanceof IcingaService) {
            $hostName = $object->get('host');
            $subjects = $object->getObjectName() . " ($varName)";
        } else {
            $hostName = $object->getObjectName();
            $subjects = sprintf(
                $this->translate('%s instances'),
                $varName
            );
        }
        $this->addTitle(sprintf(
            $this->translate('%s on %s'),
            $subjects,
            $hostName
        ));
        return $subjects;
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
