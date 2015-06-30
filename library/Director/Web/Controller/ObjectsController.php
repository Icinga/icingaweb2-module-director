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


        if (! in_array($type, $this->globalTypes)) {
            if ($this->dummyObject()->isGroup()) {
                $this->getTabs()->activate('objectgroups');
            } else {
                $this->getTabs()->activate('objects');
            }
        }

        $this->view->addLink = $this->view->qlink(
            $this->translate('Add ' . ucfirst($ltype)),
            'director/' . $ltype . '/add'
        );
        $this->view->title = $this->translate('Icinga ' . ucfirst($ltype));
        $this->view->table = $this->loadTable('icinga' . ucfirst($type))
            ->setConnection($this->db());
        $this->render('objects/table', null, true);
    }

    protected function dummyObject()
    {
        if ($this->dummy === null) {
            $class = $this->getObjectClassname();
            $this->dummy = $class::create(array());
        }

        return $this->dummy;
    }

    protected function getType()
    {
        // Strip final 's' and upcase an eventual 'group'
        return preg_replace(
            array('/group$/', '/period$/', '/argument$/'),
            array('Group', 'Period', 'Argument'),
            substr($this->getRequest()->getControllerName(), 0, -1)
        );
    }

    protected function getObjectClassname()
    {
        return 'Icinga\\Module\\Director\\Objects\\Icinga'
            . ucfirst($this->getType());
    }
}
