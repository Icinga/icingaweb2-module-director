<?php

namespace Icinga\Module\Director\Web\Controller;

abstract class ObjectsController extends ActionController
{
    protected $dummy;

    protected $isApified = true;

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

        $object = $this->dummyObject();
        if ($object->isGroup()) {
            $type = substr($type, 0, -5);
        }

        $tabs = $this->getTabs()->add('objects', array(
            'url'   => sprintf('director/%ss', strtolower($type)),
            'label' => $this->translate(ucfirst($type) . 's'),
        ));
        if ($object->supportsGroups() || $object->isGroup()) {
            $tabs->add('objectgroups', array(
                'url'   => sprintf('director/%sgroups', $type),
                'label' => $this->translate('Groups')
            ));
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
        $dummy = $this->dummyObject();

        if (! in_array(ucfirst($type), $this->globalTypes)) {
            if ($dummy->isGroup()) {
                $this->getTabs()->activate('objectgroups');
                $table = 'icinga' . ucfirst($type);
            } else {
                $this->getTabs()->activate('objects');
                $table = 'icinga' . ucfirst($type);
            }
        } else {
            $table = 'icinga' . ucfirst($type);
        }

        if ($dummy->isTemplate()) {
            $addParams = array('type' => 'template');
            $addTitle = $this->translate('Add %s template');
        } else {
            $addParams = array();
            $addTitle = $this->translate('Add %s');
        }

        $this->view->addLink = $this->view->qlink(
            sprintf($addTitle, $this->translate(ucfirst($ltype))),
            'director/' . $ltype .'/add',
            $addParams,
            array('class' => 'icon-plus')
        );

        $this->view->title = $this->translate('Icinga ' . ucfirst($ltype) . 's');
        $table = $this->loadTable($table)->setConnection($this->db());
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

    public function templatetreeAction()
    {
        $this->getTabs()->activate('tree');
        $this->view->tree = $this->db()->fetchTemplateTree(strtolower($this->getType()));
        $this->view->objectTypeName = $this->getType();
        $this->setViewScript('objects/tree');
    }

    protected function dummyObject()
    {
        if ($this->dummy === null) {
            $class = $this->getObjectClassname();
            $this->dummy = $class::create(array());
            if ($this->dummy->hasProperty('object_type')) {
                if (false === strpos($this->getRequest()->getControllerName(), 'template')) {
                    $this->dummy->object_type = 'object';
                } else {
                    $this->dummy->object_type = 'template';
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
