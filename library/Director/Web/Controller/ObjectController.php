<?php

namespace Icinga\Module\Director\Web\Controller;

use Exception;
use Icinga\Exception\NotFoundError;
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

            $tabs->add('render', array(
                'url'       => sprintf('director/%s/render', $type),
                'urlParams' => $params,
                'label'     => $this->translate('Preview'),
            ))->add('history', array(
                'url'       => sprintf('director/%s/history', $type),
                'urlParams' => $params,
                'label'     => $this->translate('History')
            ));

            if ($object->hasBeenLoadedFromDb()
                && $object->supportsFields()
                && ($object->isTemplate() || $type === 'command')
            ) {
                $tabs->add('fields', array(
                    'url'       => sprintf('director/%s/fields', $type),
                    'urlParams' => $params,
                    'label'     => $this->translate('Fields')
                ));
            }
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
            try {
                return $this->handleApiRequest();
            } catch (Exception $e) {
                return $this->sendJson((object) array('error' => $e->getMessage()));
            }
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

        $this->view->title = sprintf($this->translate('Modify %s'), ucfirst($ltype));
        $this->view->form->handleRequest();

        $this->view->actionLinks = $this->view->icon('paste')
            . ' '
            . $this->view->qlink(
                sprintf($this->translate('Clone'), $this->translate(ucfirst($ltype))),
                'director/' . $ltype .'/clone',
                array('name' => $object->object_name)
            );

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

        $this->view->title = sprintf(
            $this->translate('Add new Icinga %s'),
            ucfirst($ltype)
        );

        $form->handleRequest();
        $this->render('object/form', null, true);
    }

    public function cloneAction()
    {
        $type = $this->getType();
        $ltype = strtolower($type);
        $this->getTabs()->activate('modify');

        $this->view->form = $form = $this->loadForm(
            'icingaCloneObject'
        )->setObject($this->object);

        $this->view->title = sprintf(
            $this->translate('Clone Icinga %s'),
            ucfirst($type)
        );
        $this->view->form->handleRequest();

        $this->view->actionLinks = $this->view->icon('left-big')
            . ' '
            . $this->view->qlink(
                sprintf($this->translate('back'), $this->translate(ucfirst($ltype))),
                'director/' . $ltype,
                array('name' => $this->object->object_name)
            );

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

    protected function handleApiRequest()
    {
        $request = $this->getRequest();
        $db = $this->db();

        switch ($request->getMethod()) {
            case 'DELETE':
                $this->requireObject();
                $name = $this->object->object_name;
                $obj = $this->object->toPlainObject(false, true);
                $form = $this->loadForm(
                    'icingaDeleteObject'
                )->setObject($this->object)->setRequest($request)->onSuccess();

                return $this->sendJson($obj);

            case 'POST':
            case 'PUT':
                $type = $this->getType();
                $data = (array) json_decode($request->getRawBody());
                if ($object = $this->object) {
                    if ($request->getMethod() === 'POST') {
                        $object->setProperties($data);
                    } else {
                        $data = array_merge(
                            array(
                                'object_type' => $object->object_type,
                                'object_name' => $object->object_name
                            ),
                            $data
                        );
                        $object->replaceWith(
                            IcingaObject::createByType($type, $data, $db)
                        );
                    }
                } else {
                    $object = IcingaObject::createByType($type, $data, $db);
                }

                $response = $this->getResponse();
                if ($object->hasBeenModified()) {
                    $object->store();
                    if ($object->hasBeenLoadedFromDb()) {
                        $response->setHttpResponseCode(200);
                    } else {
                        $response->setHttpResponseCode(201);
                    }
                } else {
                    $response->setHttpResponseCode(304);
                }

                return $this->sendJson($object->toPlainObject(false, true));

            case 'GET':
                $this->requireObject();
                return $this->sendJson(
                    $this->object->toPlainObject(
                        $this->params->shift('resolved'),
                        ! $this->params->shift('withNull'),
                        $this->params->shift('properties')
                    )
                );

            default:
                throw new Exception('Unsupported method ' . $request->getMethod());
        }
    }

    protected function requireObject()
    {
        if (! $this->object) {
            $this->getResponse()->setHttpResponseCode(404);
            if (! $this->params->get('name')) {
                throw new NotFoundError('You need to pass a "name" parameter to access a specific object');
            } else {
                throw new NotFoundError('No such object available');
            }
        }
    }
}
