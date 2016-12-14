<?php

namespace Icinga\Module\Director\Web\Controller;

use Icinga\Data\Filter\FilterChain;
use Icinga\Data\Filter\FilterExpression;
use Icinga\Exception\NotFoundError;
use Icinga\Data\Filter\Filter;
use Icinga\Module\Director\Objects\IcingaObject;

abstract class NewObjectsController extends ActionController
{
    /** @var IcingaObject */
    protected $dummy;

    protected $singleTypeName;

    protected $pluralTypeName;

    protected $isApified = true;

    protected $multiEdit = array();

    public function init()
    {
        parent::init();
        $this->populateDeploymentInfo();
    }

    public function indexAction()
    {
        $this->singleTab($this->translate($this->getPluralTypeName()));
        if (! $this->getRequest()->isApiRequest()) {
            $this->setAutorefreshInterval(10);
        }
        $this->setTableTitle()
            ->addAddLink()
            ->loadRequiredTable();
    }

    public function applyAction()
    {
        $this->assertPermission('director/admin');
        $this->indexAction();
    }

    public function templatesAction()
    {
        $this->assertPermission('director/admin');
        $this->indexAction();
    }

    /**
     * You'll reach this when selecting multiple objects at once
     *
     * @throws NotFoundError
     */
    public function editAction()
    {
        // TODO: Should we assert any permissions here?

        $type = ucfirst($this->getType());

        if (empty($this->multiEdit)) {
            throw new NotFoundError(
                'Cannot edit multiple "%s" instances',
                $this->getSingleTypeName()
            );
        }

        $formName = 'icinga' . $type;

        $this->singleTab($this->translate('Multiple objects'));
        $filter = Filter::fromQueryString($this->params->toString());
        $dummy = $this->dummyObject();
        $objects = array();
        $db = $this->db();
        /** @var $filter FilterChain */
        foreach ($filter->filters() as $sub) {
            /** @var $sub FilterChain */
            foreach ($sub->filters() as $ex) {
                /** @var $ex FilterChain|FilterExpression */
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

    public function setsAction()
    {
        $this->assertPermission('director/admin');

        $this->singleTab($this->translate($this->getPluralTypeName()));
        $dummy = $this->dummyObject();
        $type = $this->getType();

        if ($dummy->supportsSets() !== true) {
            throw new NotFoundError('Sets are not available for %s', $type);
        }

        $this->view->title = sprintf(
            $this->translate('Icinga %s Sets'),
            $this->getPluralTypeName()
        );
        $table = $this->loadTable('Icinga' . ucfirst($type) . 'Set')
            ->setConnection($this->db());

        $this->view->addLink = $this->view->qlink(
            $this->translate('Add'),
            'director/' . $type . 'set/add',
            null,
            array(
                'class'            => 'icon-plus',
                'data-base-target' => '_next'
            )
        );

        $this->provideFilterEditorForTable($table, $dummy);
        $this->getTabs()->activate('sets');
        $this->setViewScript('objects/table');
    }

    protected function addAddLink()
    {
        $dummy = $this->dummyObject();
        if ($dummy->isTemplate()) {
            $objectType = 'template';
        } elseif ($dummy->isApplyRule()) {
            $objectType = 'apply';
        } else {
            $objectType = 'object';
        }

        $addParams = array('type' => $objectType);
        $this->view->addLink = $this->view->qlink(
            $this->translate('Add'),
            'director/' . $this->getType() .'/add',
            $addParams,
            array('class' => 'icon-plus')
        );

        return $this;
    }

    protected function setTableTitle()
    {
        if ($this->dummyObject()->isTemplate()) {
            $title = sprintf(
                $this->translate('Icinga %s Templates'),
                $this->getSingleTypeName()
            );
        } elseif ($this->dummyObject()->isApplyRule()) {
            $title = sprintf(
                $this->translate('Apply Icinga %s'),
                $this->getPluralTypeName()
            );
        } else {
            $title = sprintf(
                $this->translate('Icinga %s'),
                $this->getPluralTypeName()
            );
        }

        $this->view->title = $title;

        return $this;
    }

    protected function loadRequiredTable()
    {
        $type = $this->getType();

        $dummy = $this->dummyObject();
        $table = 'icinga' . ucfirst($type);
        if ($dummy->isTemplate()) {
            // Trick the autoloader - inheritance would fail otherwise
            $table = 'icinga' . ucfirst($type);
            $this->loadTable($table);
            $table .= 'Template';
        }

        $table = $this->loadTable($table)->setConnection($this->db());
        if ($dummy->isTemplate()) {
            $table->enforceFilter(Filter::expression('object_type', '=', 'template'));
        } else {
            if ($dummy->supportsImports()
                && array_key_exists('object_type', $table->getColumns())
            ) {
                $table->enforceFilter(Filter::expression('object_type', '!=', 'template'));
            }
        }

        $this->provideFilterEditorForTable($table, $dummy);
        $this->setViewScript('objects/table');

        return $this;
    }

    /**
     * Provide information required to eventually show a deployment link
     */
    protected function populateDeploymentInfo()
    {
        $this->view->totalUndeployedChanges = $this->db()
            ->countActivitiesSinceLastDeployedConfig();

        return $this;
    }

    /**
     * @return IcingaObject
     */
    protected function dummyObject()
    {
        if ($this->dummy === null) {
            $controller = $this->getRequest()->getControllerName();
            $action = $this->getRequest()->getActionName();
            $both = $controller . ' ' . $action;
            /** @var IcingaObject $class */
            $class = $this->getObjectClassname();
            $this->dummy = $class::create(array());
            if (strpos($both, 'template') !== false) {
                $this->dummy->set('object_type', 'template');
            } elseif (strpos($both, 'apply') !== false) {
                $this->dummy->set('object_type', 'apply');
            } else {
                $this->dummy->set('object_type', 'object');
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

    protected function getSingleTypeName()
    {
        if ($this->singleTypeName === null) {
            // Strip final 's' and separate words
            $this->singleTypeName = ucfirst(preg_replace(
                array('/group$/', '/period$/', '/argument$/', '/apiuser$/'),
                array(' group', ' period', ' argument', 'Api user'),
                str_replace(
                    'template',
                    '',
                    substr($this->getRequest()->getControllerName(), 0, -1)
                )
            ));
        }

        return $this->singleTypeName;
    }

    protected function getPluralTypeName()
    {
        if ($this->pluralTypeName === null) {
            $this->pluralTypeName = $this->getSingleTypeName() . 's';
        }

        return $this->pluralTypeName;
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
