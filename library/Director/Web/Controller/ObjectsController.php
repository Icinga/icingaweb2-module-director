<?php

namespace Icinga\Module\Director\Web\Controller;

use Icinga\Data\Filter\FilterChain;
use Icinga\Data\Filter\FilterExpression;
use Icinga\Exception\NotFoundError;
use Icinga\Data\Filter\Filter;
use Icinga\Module\Director\Objects\IcingaObject;
use Icinga\Module\Director\Web\Table\IcingaObjectTable;
use Icinga\Module\Director\Web\Table\QuickTable;
use Icinga\Web\Widget\FilterEditor;

abstract class ObjectsController extends ActionController
{
    /** @var IcingaObject */
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
            /** @var IcingaObject $baseType */
            $baseType = $this->getObjectClassname($type);
            $baseObject = $baseType::create(array());
        } else {
            $baseObject = $object;
        }

        $tabs->add('objects', array(
            'url'   => sprintf('director/%ss', strtolower($type)),
            'label' => $this->translate(ucfirst($type) . 's'),
        ));

        if ($this->hasPermission('director/admin')) {
            if ($object->supportsImports()) {
                $tabs->add('templates', array(
                    'url'   => sprintf('director/%ss/templates', strtolower($type)),
                    'label' => $this->translate('Templates'),
                ));
            }

            if ($baseObject->supportsGroups()) {
                $tabs->add('objectgroups', array(
                    'url'   => sprintf('director/%sgroups', $type),
                    'label' => $this->translate('Groups')
                ));
            }

            if ($baseObject->supportsSets()) {
                 $tabs->add('sets', array(
                      'url'    => sprintf('director/%ss/sets', $type),
                      'label' => $this->translate('Sets')
                 ));
            }

            $tabs->add('tree', array(
                'url'   => sprintf('director/%ss/templatetree', $type),
                'label' => $this->translate('Tree'),
            ));
        }
    }

    public function indexAction()
    {
        if (! $this->getRequest()->isApiRequest()) {
            $this->setAutorefreshInterval(10);
        }

        $type = $this->getType();
        $ltype = strtolower($type);
        /** @var IcingaObject $dummy */
        $dummy = $this->dummyObject();

        if (! in_array(ucfirst($type), $this->globalTypes)) {
            if ($dummy->isGroup()) {
                $this->getTabs()->activate('objectgroups');
                $table = 'icinga' . ucfirst($type);
            } elseif ($dummy->isTemplate()) {
                $this->getTabs()->activate('templates');
                // Trick the autoloader
                $table = 'icinga' . ucfirst($type);
                $this->loadTable($table);
                $table .= 'Template';
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

        $this->provideFilterEditorForTable($table, $dummy);
        $this->setViewScript('objects/table');
    }

    public function editAction()
    {
        $type = ucfirst($this->getType());

        if (empty($this->multiEdit)) {
            throw new NotFoundError('Cannot edit multiple "%s" instances', $type);
        }
        $formName = 'icinga' . $type;

        $objects = $this->loadMultiObjectsFromParams();
        $this->singleTab($this->translate('Multiple objects'));

        $this->view->title = sprintf(
            $this->translate('Modify %d objects'),
            count($objects)
        );

        $this->view->form = $form = $this->loadForm('IcingaMultiEdit')
            ->setObjects($objects)
            ->pickElementsFrom($this->loadForm($formName), $this->multiEdit);
        if ($type === 'Service') {
            $form->setListUrl('director/servicetemplate/hosts');
        }

        $form->handleRequest();
        $this->view->totalUndeployedChanges = $this->db()
            ->countActivitiesSinceLastDeployedConfig();
        $this->setViewScript('objects/form');
    }

    protected function loadMultiObjectsFromParams()
    {
        $filter = Filter::fromQueryString($this->params->toString());
        $dummy = $this->dummyObject();
        $objects = array();
        $db = $this->db();
        /** @var $filter FilterChain */
        foreach ($filter->filters() as $sub) {
            /** @var $sub FilterChain */
            foreach ($sub->filters() as $ex) {
                /** @var $ex FilterChain|FilterExpression */
                $col = $ex->getColumn();
                if ($ex->isExpression()) {
                    if ($col === 'name') {
                        $name = $ex->getExpression();
                        $objects[$name] = $dummy::load($name, $db);
                    } elseif ($col === 'id') {
                        $name = $ex->getExpression();
                        $objects[$name] = $dummy::load(['id' => $name], $db);
                    }
                }
            }
        }

        return $objects;
    }

    public function templatesAction()
    {
        $this->assertPermission('director/admin');
        $this->indexAction();
    }

    public function templatetreeAction()
    {
        $this->assertPermission('director/admin');
        $this->setAutorefreshInterval(10);
        $this->getTabs()->activate('tree');
        $this->view->tree = $this->db()->fetchTemplateTree(strtolower($this->getType()));
        $this->view->objectTypeName = $this->getType();
        $this->setViewScript('objects/tree');
    }

    public function setsAction()
    {
        $this->assertPermission('director/admin');

        $dummy = $this->dummyObject();
        $type = $this->getType();
        $Type = ucfirst($type);

        if ($dummy->supportsSets() !== true) {
            throw new NotFoundError('Sets are not available for %s', $type);
        }

        $this->view->title = $this->translate('Icinga ' . $Type . ' Sets');
        $table = $this->loadTable('Icinga' . $Type . 'Set')->setConnection($this->db());

        $this->view->addLink = $this->view->qlink(
            $this->translate('Add'),
            'director/' . $type . 'set/add',
            null,
            array(
                'class'            => 'icon-plus',
                'data-base-target' => '_next'
            )
        );

        $this->provideFilterEditorForTable($table);
        $this->getTabs()->activate('sets');
        $this->setViewScript('objects/table');
    }

    /**
     * @return IcingaObject
     */
    protected function dummyObject()
    {
        if ($this->dummy === null) {
            /** @var IcingaObject $class */
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

    protected function getObjectClassname($type = null)
    {
        if ($type === null) {
            $type = $this->getType();
        }
        return 'Icinga\\Module\\Director\\Objects\\Icinga'
            . ucfirst($type);
    }
}
