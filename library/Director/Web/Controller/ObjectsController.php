<?php

namespace Icinga\Module\Director\Web\Controller;

use Icinga\Exception\NotFoundError;
use Icinga\Data\Filter\Filter;
use Icinga\Module\Director\Objects\IcingaObject;
use Icinga\Module\Director\Web\Table\IcingaObjectTable;

abstract class ObjectsController extends ActionController
{
    protected $dummy;

    protected $isApified = true;

    protected $multiEdit = array();

    protected $globalTypes = array(
        'ApiUser',
        'Zone',
        'Endpoint',
        'TimePeriod',
    );

    public function init()
    {
        parent::init();

        $tabs = $this->getTabs();
        $type = $this->getType();

        if (in_array(ucfirst($type), $this->globalTypes)) {
            $ltype = strtolower($type);

            foreach ($this->globalTypes as $tabType) {
                $ltabType = strtolower($tabType);
                $tabs->add($ltabType, array(
                    'label' => $this->translate(ucfirst($ltabType) . 's'),
                    'url'   => sprintf('director/%ss', $ltabType)
                ));
            }
            $tabs->activate($ltype);

            return;
        }

        /** @var IcingaObject $object */
        $object = $this->dummyObject();
        if ($object->isGroup()) {
            $type = substr($type, 0, -5);
        }

        $tabs->add('objects', array(
            'url'   => sprintf('director/%ss', strtolower($type)),
            'label' => $this->translate(ucfirst($type) . 's'),
        ));
        if ($object->supportsImports()) {
            $tabs->add('templates', array(
                'url'   => sprintf('director/%ss/templates', strtolower($type)),
                'label' => $this->translate('Templates'),
            ));
        }
        if ($object->supportsGroups() || $object->isGroup()) {
            $tabs->add('objectgroups', array(
                'url'   => sprintf('director/%sgroups', $type),
                'label' => $this->translate('Groups')
            ));
        }

        if ($object->supportsSets() || $object->isGroup() /** Bullshit, need base object, wrong on users */) {
            /** forced to master, disabled for now
            $tabs->add('sets', array(
                'url'       => sprintf('director/%ss/sets', $type),
                'label'     => $this->translate('Sets')
            ));
            */
        }

        $tabs->add('tree', array(
            'url'   => sprintf('director/%ss/templatetree', $type),
            'label' => $this->translate('Tree'),
        ));
    }

    public function indexAction()
    {
        if (! $this->getRequest()->isApiRequest()) {
            $this->setAutorefreshInterval(10);
        }

        $type = $this->getType();
        $ltype = strtolower($type);
        $this->assertPermission('director/' . $type . 's/read');
        /** @var IcingaObject $dummy */
        $dummy = $this->dummyObject();

        if (! in_array(ucfirst($type), $this->globalTypes)) {
            if ($dummy->isGroup()) {
                $this->getTabs()->activate('objectgroups');
                $table = 'icinga' . ucfirst($type);
            } elseif ($dummy->isTemplate()) {
                $this->getTabs()->activate('templates');
                $table = 'icinga' . ucfirst($type);
            } else {
                $this->getTabs()->activate('objects');
                $table = 'icinga' . ucfirst($type);
            }
        } else {
            $table = 'icinga' . ucfirst($type);
        }

        /** @var IcingaObjectTable $table */
        $table = $this->loadTable($table)->setConnection($this->db());

        if ($dummy->isTemplate()) {
            $addParams = array('type' => 'template');
            $this->getTabs()->activate('templates');
            $title = $this->translate('Icinga ' . ucfirst($ltype) . ' Templates');
            $table->enforceFilter(Filter::expression('object_type', '=', 'template'));
        } else {
            $addParams = array('type' => 'object');
            $title = $this->translate('Icinga ' . ucfirst($ltype) . 's');
            if ($dummy->supportsImports()
                && array_key_exists('object_type', $table->getColumns())
                && ! in_array(ucfirst($type), $this->globalTypes)
            ) {
                $table->enforceFilter(Filter::expression('object_type', '!=', 'template'));
            }
        }

        $this->view->title = $title;

        $this->view->addLink = $this->view->qlink(
            $this->translate('Add'),
            'director/' . $ltype .'/add',
            $addParams,
            array('class' => 'icon-plus')
        );

        $filterEditor = $table->getFilterEditor($this->getRequest());
        $filter = $filterEditor->getFilter();

        if ($filter->isEmpty()) {

            if ($this->params->get('modifyFilter')) {
                $this->view->addLink .= ' ' . $this->view->qlink(
                    $this->translate('Show unfiltered'),
                    $this->getRequest()->getUrl()->setParams(array()),
                    null,
                    array(
                        'class' => 'icon-cancel',
                        'data-base-target' => '_self',
                    )
                );
            } else {
                $this->view->addLink .= ' ' . $this->view->qlink(
                    $this->translate('Filter'),
                    $this->getRequest()->getUrl()->with('modifyFilter', true),
                    null,
                    array(
                        'class' => 'icon-search',
                        'data-base-target' => '_self',
                    )
                );
            }

        } else {

            $this->view->addLink .= ' ' . $this->view->qlink(
                $this->shorten($filter, 32),
                $this->getRequest()->getUrl()->with('modifyFilter', true),
                null,
                array(
                    'class' => 'icon-search',
                    'data-base-target' => '_self',
                )
            );

            $this->view->addLink .= ' ' . $this->view->qlink(
                $this->translate('Show unfiltered'),
                $this->getRequest()->getUrl()->setParams(array()),
                null,
                array(
                    'class' => 'icon-cancel',
                    'data-base-target' => '_self',
                )
            );
        }

        if ($this->params->get('modifyFilter')) {
            $this->view->filterEditor = $filterEditor;
        }

        if ($this->getRequest()->isApiRequest()) {
            $objects = array();
            foreach ($dummy::loadAll($this->db) as $object) {
                $objects[] = $object->toPlainObject(false, true);
            }
            return $this->sendJson((object) array('objects' => $objects));
        }

        $this->view->table = $this->applyPaginationLimits($table);

        $this->provideQuickSearch();

        $this->setViewScript('objects/table');
    }

    public function editAction()
    {
        $type = ucfirst($this->getType());

        if (empty($this->multiEdit)) {
            throw new NotFoundError('Cannot edit multiple "%s" instances', $type);
        }
        $formName = 'icinga' . $type;

        $this->singleTab($this->translate('Multiple objects'));
        $filter = Filter::fromQueryString($this->params->toString());
        $dummy = $this->dummyObject();
        $objects = array();
        $db = $this->db();
        foreach ($filter->filters() as $sub) {
            foreach ($sub->filters() as $ex) {
                if ($ex->isExpression() && $ex->getColumn() === 'name') {
                    $name = $ex->getExpression();
                    $objects[$name] = $dummy::load($name, $db);
                }
            }
        }
        $this->view->title = sprintf(
            $this->translate('Modify %d objects'),
            count($objects)
        );

        $this->view->form = $this->loadForm('IcingaMultiEdit')
            ->setObjects($objects)
            ->pickElementsFrom($this->loadForm($formName), $this->multiEdit)
            ->handleRequest();

        $this->setViewScript('objects/form');
    }

    public function templatesAction()
    {
        $this->indexAction();
    }

    public function templatetreeAction()
    {
        $this->setAutorefreshInterval(10);
        $this->getTabs()->activate('tree');
        $this->view->tree = $this->db()->fetchTemplateTree(strtolower($this->getType()));
        $this->view->objectTypeName = $this->getType();
        $this->setViewScript('objects/tree');
    }

    public function setsAction()
    {
        $this->view->title = $this->translate('Service sets');
        $this->view->table = $this
            ->loadTable('IcingaServiceSet')
            ->setConnection($this->db());

        $this->view->addLink = $this->view->qlink(
            $this->translate('Add'),
            'director/serviceset/add',
            null,
            array(
                'class'            => 'icon-plus',
                'data-base-target' => '_next'
            )
        );

        $this->getTabs()->activate('sets');
        $this->setViewScript('objects/table');
    }

    protected function dummyObject()
    {
        if ($this->dummy === null) {
            $class = $this->getObjectClassname();
            $this->dummy = $class::create(array());
            if ($this->dummy->hasProperty('object_type')) {
                if (strpos($this->getRequest()->getControllerName(), 'template') !== false
                    || strpos($this->getRequest()->getActionName(), 'templates') !== false
                ) {
                    $this->dummy->object_type = 'template';
                } else {
                    $this->dummy->object_type = 'object';
                }
            }
        }

        return $this->dummy;
    }

    protected function getType()
    {
        // Strip final 's' and upcase an eventual 'group'
        return preg_replace(
            array('/group$/', '/period$/', '/argument$/', '/apiuser$/'),
            array('Group', 'Period', 'Argument', 'ApiUser'),
            str_replace(
                'template',
                '',
                substr($this->getRequest()->getControllerName(), 0, -1)
            )
        );
    }

    protected function getObjectClassname()
    {
        return 'Icinga\\Module\\Director\\Objects\\Icinga'
            . ucfirst($this->getType());
    }
}
