<?php

namespace Icinga\Module\Director\Web\Controller;

use Icinga\Web\Url;

abstract class ObjectController extends ActionController
{
    protected $object;

    public function init()
    {
        $type = $this->getType();
        $ltype = strtolower($type);
        $params = array();
        if ($name = $this->params->get('name')) {
            $params['name'] = $name;

            $this->getTabs()->add($type, array(
                'url'       => sprintf('director/%s', $ltype),
                'urlParams' => $params,
                'label'     => $this->translate(ucfirst($ltype)),
            ))->add('modify', array(
                'url'       => sprintf('director/%s/edit', $ltype),
                'urlParams' => $params,
                'label'     => $this->translate('Modify')
            ))->add('history', array(
                'url'       => sprintf('director/%s/history', $ltype),
                'urlParams' => $params,
                'label'     => $this->translate('History')
            ));
        } else {
            $this->getTabs()->add('add', array(
                'url'       => sprintf('director/%s', $type),
                'label'     => sprintf($this->translate('Add %s'), ucfirst($type)),
            ));
        }
    }

    public function indexAction()
    {
        $type = $this->getType();
        $this->getTabs()->activate($type);
        $this->view->object = $this->object();
        $this->render('object/show', null, true);
    }

    public function editAction()
    {
        $this->getTabs()->activate('modify');
        $type = $this->getType();
        $ltype = strtolower($type);

        $this->view->form = $form = $this->loadForm(
            'icinga' . ucfirst($type)
        )->setDb($this->db());
        $form->loadObject($this->params->get('name'));

        $url = Url::fromPath(
            sprintf('director/%s', $ltype),
            array('name' => $form->getObject()->object_name)
        );
        $form->setSuccessUrl($url);

        $this->view->title = sprintf(
            $this->translate('Modify Icinga %s'),
            ucfirst($ltype)
        );
        $this->view->form->handleRequest();
        $this->render('object/form', null, true);
    }

    public function addAction()
    {
        $this->getTabs()->activate('add');
        $type = $this->getType();
        $ltype = strtolower($type);

        $url = sprintf('director/%ss', $ltype);
        $this->view->form = $this->loadForm('icinga' . ucfirst($type))
            ->setDb($this->db())
            ->setSuccessUrl($url);

        $this->view->title = sprintf(
            $this->translate('Add new Icinga %s'),
            ucfirst($ltype)
        );
        $this->view->form->handleRequest();
        $this->render('object/form', null, true);
    }

    public function historyAction()
    {
        $type = $this->getType();
        $this->getTabs()->activate('history');
        $object = $this->object();
        $this->view->title = $this->translate('Activity Log');
        $this->view->table = $this->applyPaginationLimits(
            $this->loadTable('activityLog')->setConnection($this->db())
            ->filterObject('icinga_' . $type, $object->object_name)
        );
        $this->render('object/history', null, true);
    }

    protected function getType()
    {
        // Strip final 's' and upcase an eventual 'group'
        return preg_replace(
            array('/group$/', '/period$/', '/argument$/'),
            array('Group', 'Period', 'Argument'),
            $this->getRequest()->getControllerName()
        );
    }

    protected function object()
    {
        if ($name = $this->params->get('name')) {
            $this->object = $this->loadObject($name);
        }

        return $this->object;
    }

    protected function getObjectClassname()
    {
        return 'Icinga\\Module\\Director\\Objects\\Icinga'
            . ucfirst($this->getType());
    }

    protected function loadObject($id)
    {
        $class = $this->getObjectClassname();
        $object = $class::load($id, $this->db());
        $this->view->title = sprintf(
            '%s "%s"',
            $this->translate(ucfirst(strtolower($this->getType()))),
            $object->object_name
        );

        return $object;
    }
}
