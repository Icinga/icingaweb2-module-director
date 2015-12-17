<?php

namespace Icinga\Module\Director\Web\Controller;

use Icinga\Web\Url;
use Icinga\Module\Director\Objects\IcingaObject;

abstract class ObjectController extends ActionController
{
    protected $object;

    protected $isApified = true;

    public function init()
    {
        parent::init();

        $type = $this->getType();

        $params = array();
        if ($object = $this->loadObject()) {

            $params['name'] = $object->object_name;

            $tabs = $this->getTabs()->add('modify', array(
                'url'       => sprintf('director/%s/edit', $type),
                'urlParams' => $params,
                'label'     => $this->translate(ucfirst($type))
            ));

            if ($object->hasBeenLoadedFromDb()
                && $object->supportsFields()
                && $object->isTemplate()
            ) {
                $tabs->add('fields', array(
                    'url'       => sprintf('director/%s/fields', $type),
                    'urlParams' => $params,
                    'label'     => $this->translate('Fields')
                ));
            }

            $tabs->add('render', array(
                'url'       => sprintf('director/%s/render', $type),
                'urlParams' => $params,
                'label'     => $this->translate('Preview'),
            ))->add('history', array(
                'url'       => sprintf('director/%s/history', $type),
                'urlParams' => $params,
                'label'     => $this->translate('History')
            ));
        } else {
            $this->getTabs()->add('add', array(
                'url'       => sprintf('director/%s/add', $type),
                'label'     => sprintf($this->translate('Add %s'), ucfirst($type)),
            ));
        }
    }

    public function indexAction()
    {
        if ($this->getRequest()->isApiRequest()) {
            return $this->sendJson(
                $this->object->toPlainObject($this->params->shift('resolved'))
            );
        }

        return $this->editAction();
    }

    public function renderAction()
    {
        $type = $this->getType();
        $this->getTabs()->activate('render');
        $this->view->object = $this->object;
        $this->view->title = sprintf(
            $this->translate('Config preview: %s'),
            $this->object->object_name
        );
        $this->render('object/show', null, true);
    }

    // TODO: Remove or leave here for API access only. Probably not needed.
    public function deleteAction()
    {
        $type = $this->getType();
        $this->getTabs()->activate('delete');

        $this->view->form = $form = $this->loadForm(
            'icingaDeleteObject'
        )->setObject($this->object);

        $url = Url::fromPath(sprintf('director/%ss', $type));
        $form->setSuccessUrl($url);

        $this->view->title = sprintf(
            $this->translate('Delete Icinga %s'),
            ucfirst($type)
        );
        $this->view->form->handleRequest();
        $this->render('object/form', null, true);
    }

    public function editAction()
    {
        $object = $this->object;
        $this->getTabs()->activate('modify');
        $ltype = $this->getType();
        $type = ucfirst($ltype);

        $formName = 'icinga' . $type;
        $this->view->form = $form = $this->loadForm($formName)->setDb($this->db());
        $form->setObject($object);

        $url = Url::fromPath(
            sprintf('director/%s', $type),
            array('name' => $object->object_name)
        );
        $form->setSuccessUrl($url);

        if ($object->isTemplate()) {
            $title = $this->translate('Modify Icinga %s template');
            $form->setObjectType('template'); // WHY??
        } else {
            $title = $this->translate('Modify Icinga %s');
        }

        $this->view->title = sprintf($title, ucfirst($ltype));
        $this->view->form->handleRequest();

        $this->render('object/form', null, true);
    }

    public function addAction()
    {
        $this->getTabs()->activate('add');
        $type = $this->getType();
        $ltype = strtolower($type);

        $url = sprintf('director/%ss', $ltype);
        $form = $this->view->form = $this->loadForm('icinga' . ucfirst($type))
            ->setDb($this->db())
            ->setSuccessUrl($url);

        if ($this->params->get('type') === 'template') {
            $form->setObjectType('template');
            $title = $this->translate('Add new Icinga %s template');
        } else {
            $title = $this->translate('Add new Icinga %s');
        }

        $this->view->title = sprintf($title, ucfirst($ltype));
        $form->handleRequest();
        $this->render('object/form', null, true);
    }

    public function cloneAction()
    {
        $type = $this->getType();
        $this->getTabs()->activate('modify');

        $this->view->form = $form = $this->loadForm(
            'icingaCloneObject'
        )->setObject($this->object);

        $this->view->title = sprintf(
            $this->translate('Clone Icinga %s'),
            ucfirst($type)
        );
        $this->view->form->handleRequest();
        $this->render('object/form', null, true);
    }

    public function fieldsAction()
    {
        $object = $this->object;
        $type = $this->getType();

        $this->getTabs()->activate('fields');
        $title = $this->translate('%s template "%s": custom fields');
        $this->view->title = sprintf(
            $title,
            $this->translate(ucfirst($type)),
            $object->object_name
        );

        $form = $this->view->form = $this
            ->loadForm('icingaObjectField')
            ->setDb($this->db)
            ->setIcingaObject($object);

        if ($id = $this->params->get('field_id')) {
            $form->loadObject(array(
                $type . '_id' => $object->id,
                'datafield_id' => $id
            ));
        }

        $form->handleRequest();

        $this->view->table = $this
            ->loadTable('icingaObjectDatafield')
            ->setObject($object);

        $this->render('object/fields', null, true);
    }

    public function historyAction()
    {
        $type = $this->getType();
        $this->getTabs()->activate('history');
        $this->view->title = $this->translate('Activity Log');
        $this->view->table = $this->applyPaginationLimits(
            $this->loadTable('activityLog')->setConnection($this->db())
            ->filterObject('icinga_' . $type, $this->object->object_name)
        );
        $this->render('object/history', null, true);
    }

    protected function getType()
    {
        // Strip final 's' and upcase an eventual 'group'
        return preg_replace(
            array('/group$/', '/period$/', '/argument$/', '/apiuser$/'),
            array('Group', 'Period', 'Argument', 'ApiUser'),
            $this->getRequest()->getControllerName()
        );
    }

    protected function loadObject()
    {
        if ($this->object === null && $name = $this->params->get('name')) {
            $this->object = IcingaObject::loadByType(
                $this->getType(),
                $name,
                $this->db()
            );
        }

        return $this->object;
    }
}
