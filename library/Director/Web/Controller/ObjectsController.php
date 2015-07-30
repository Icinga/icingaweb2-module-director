<?php

namespace Icinga\Module\Director\Web\Controller;

abstract class ObjectsController extends ActionController
{
    protected $dummy;

    protected $globalTypes = array(
        'Command',
        'CommandArgument',
        'TimePeriod',
        'Zone',
        'Endpoint'
    );

    public function init()
    {
        $type = $this->getType();
        $ltype = strtolower($type);

        $object = $this->dummyObject();

        if (in_array(ucfirst($type), $this->globalTypes)) {

            $tabs = $this->getTabs();
            foreach ($this->globalTypes as $tabType) {
                $ltabType = strtolower($tabType);
                $tabs->add($ltabType, array(
                    'label' => $this->translate(ucfirst($ltabType) . 's'),
                    'url'   => sprintf('director/%ss', $ltabType)
                ));
            }
            $tabs->activate($ltype);

        } elseif ($object->isGroup()) {

            $singleType = substr($type, 0, -5);
            $tabs = $this->getTabs()->add('objects', array(
                'url'   => sprintf('director/%ss', $singleType),
                'label' => $this->translate(ucfirst($singleType) . 's'),
            ));

            $tabs->add('objectgroups', array(
                'url'   => sprintf('director/%ss', strtolower($type)),
                'label' => $this->translate(ucfirst(strtolower($type)) . 's')
            ));

        } else {

            $tabs = $this->getTabs()->add('objects', array(
                'url'   => sprintf('director/%ss', strtolower($type)),
                'label' => $this->translate(ucfirst($type) . 's'),
            ));
            if ($object->supportsGroups()) {
                $tabs->add('objectgroups', array(
                    'url'   => sprintf('director/%sgroups', $type),
                    'label' => $this->translate(ucfirst($type) . 'groups')
                ));
            }

        }
    }

    public function indexAction()
    {
        $type = $this->getType();
        $ltype = strtolower($type);
        $dummy = $this->dummyObject();

        if (! in_array($type, $this->globalTypes)) {
            if ($dummy->isGroup()) {
                $this->getTabs()->activate('objectgroups');
                $table = 'icinga' . ucfirst($type);
            } elseif ($dummy->isTemplate()) {
                $this->getTabs()->activate('objecttemplates');
                $table = 'icinga' . ucfirst($type);
                $this->loadTable($table);
                $table = 'icinga' . ucfirst($type) . 'Template';
            } else {
                $this->getTabs()->activate('objects');
            }
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
            $addParams
        );
        $this->view->title = $this->translate('Icinga ' . ucfirst($ltype));
        $table = $this->loadTable($table)->setConnection($this->db());
        $this->setupFilterControl($table->getFilterEditor($this->getRequest()));
        $this->view->table = $this->applyPaginationLimits($table);

        $this->render('objects/table', null, true);
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
            array('/group$/', '/period$/', '/argument$/'),
            array('Group', 'Period', 'Argument'),
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
