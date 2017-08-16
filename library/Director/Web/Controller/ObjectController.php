<?php

namespace Icinga\Module\Director\Web\Controller;

use Exception;
use Icinga\Exception\IcingaException;
use Icinga\Exception\InvalidPropertyException;
use Icinga\Exception\NotFoundError;
use Icinga\Module\Director\Deployment\DeploymentInfo;
use Icinga\Module\Director\Exception\DuplicateKeyException;
use Icinga\Module\Director\Exception\NestingError;
use Icinga\Module\Director\Forms\DeploymentLinkForm;
use Icinga\Module\Director\Forms\IcingaCloneObjectForm;
use Icinga\Module\Director\Forms\IcingaObjectFieldForm;
use Icinga\Module\Director\Objects\IcingaObject;
use Icinga\Module\Director\Objects\IcingaObjectGroup;
use Icinga\Module\Director\Web\Controller\Extension\ObjectRestrictions;
use Icinga\Module\Director\Web\Form\DirectorObjectForm;
use Icinga\Module\Director\Web\Table\ActivityLogTable;
use Icinga\Module\Director\Web\Table\GroupMemberTable;
use Icinga\Module\Director\Web\Tabs\ObjectTabs;
use ipl\Html\Html;
use ipl\Html\Link;

abstract class ObjectController extends ActionController
{
    use ObjectRestrictions;

    /** @var IcingaObject */
    protected $object;

    /** @var bool This controller handles REST API requests */
    protected $isApified = true;

    /** @var array Allowed object types we are allowed to edit anyways */
    protected $allowedExternals = array(
        'apiuser',
        'endpoint'
    );

    public function init()
    {
        parent::init();

        $this->eventuallyLoadObject();
        if ($this->getRequest()->isApiRequest()) {
            $handler = new IcingaObjectHandler($this->getRequest(), $this->getResponse(), $this->db());
            $handler->setApi($this->api());
            if ($this->object) {
                $handler->setObject($this->object);
            }
            $handler->dispatch();
        } else {
            $this->tabs(new ObjectTabs($this->getType(), $this->getAuth(), $this->object));
        }
    }

    public function indexAction()
    {
        if (! $this->getRequest()->isApiRequest()) {
            $this->redirectToPreviewForExternals()
                ->editAction();
        }

        $this->editAction();
    }

    public function editAction()
    {
        $type = $this->getType();
        $object = $this->requireObject();
        $name = $object->getObjectName();
        $this->addTitle($this->translate('Template: %s'), $name);
        $this->tabs()->activate('modify');

        if ($object->isTemplate()) {
            $this->actions()->add([
                Link::create(
                    $this->translate('Usage'),
                    "director/${type}template/usage",
                    ['name' => $name],
                    ['class' => 'icon-sitemap']
                )
            ]);
        }

        $formName = 'icinga' . ucfirst($type);

        /** @var DirectorObjectForm $form */
        $form = $this->loadForm($formName);
        $form
            ->setDb($this->db())
            ->setAuth($this->Auth())
            ->setObject($object);

        $this->beforeHandlingEditRequest($form);
        $form->handleRequest();
        $this->content()->add($form);
        $this->actions()->add($this->createCloneLink());
    }

    protected function createCloneLink()
    {
        return Link::create(
            $this->translate('Clone'),
            'director/' . $this->getType() .'/clone',
            $this->object->getUrlParams(),
            array('class' => 'icon-paste')
        );
    }

    public function addAction()
    {
        $imports = $this->params->shift('imports');
        $this->tabs()->activate('add');
        $type = $this->getType();
        $ltype = strtolower($type);

        $url = sprintf('director/%ss', $ltype);
        /** @var DirectorObjectForm $form */
        $form = $this->loadForm('icinga' . ucfirst($type))
            ->setDb($this->db())
            ->setAuth($this->Auth())
            ->presetImports($imports)
            ->setSuccessUrl($url);

        if ($oType = $this->params->shift('type')) {
            $form->setPreferredObjectType($oType);
        }

        if ($oType === 'template') {
            $this->assertPermission('director/admin');
            $this->addTitle(
                $this->translate('Add new Icinga %s template'),
                ucfirst($ltype)
            );
        } else {
            $this->assertPermission("director/${ltype}s");
            if (is_string($imports) && strlen($imports)) {
                $this->addTitle(
                    $this->translate('Add %s: %s'),
                    $this->translate(ucfirst($ltype)),
                    $imports
                );
            } else {
                $this->addTitle(
                    $this->translate('Add new Icinga %s'),
                    ucfirst($ltype)
                );
            }
        }

        $this->beforeHandlingAddRequest($form);
        $form->handleRequest();
        $this->content()->add($form);
    }

    protected function beforeHandlingAddRequest($form)
    {
    }

    protected function beforeHandlingEditRequest($form)
    {
    }

    public function cloneAction()
    {
        $type = $this->getType();
        $ltype = strtolower($type);
        $this->assertPermission('director/' . $ltype);
        $this->tabs()->activate('modify');
        $this->addTitle($this->translate('Clone Icinga %s'), ucfirst($type));
        $form = IcingaCloneObjectForm::load()
            ->setObject($this->object)
            ->handleRequest();
        $this->content()->add($form);
        $this->actions()->add(Link::create(
            $this->translate('back'),
            'director/' . $ltype,
            ['name'  => $this->object->getObjectName()],
            ['class' => 'icon-left-big']
        ));
    }

    public function fieldsAction()
    {
        $this->assertPermission('director/admin');
        $object = $this->requireObject();
        $type = $this->getType();

        $this->addTitle(
            $this->translate('Custom fields: %s'),
            $object->getObjectName()
        );
        $this->tabs()->activate('fields');

        $form = IcingaObjectFieldForm::load()
            ->setDb($this->db())
            ->setIcingaObject($object);

        if ($id = $this->params->get('field_id')) {
            $form->loadObject(array(
                $type . '_id' => $object->id,
                'datafield_id' => $id
            ));

            $this->actions()->add(Link::create(
                $this->translate('back'),
                $this->url()->without('field_id'),
                null,
                ['class' => 'icon-left-big']
            ));
        }
        $form->handleRequest();

        $table = $this->loadTable('icingaObjectDatafield')->setObject($object);
        $this->content()->add([$form, $table]);
    }

    public function historyAction()
    {
        $this->assertPermission('director/audit')
            ->setAutorefreshInterval(10)
            ->tabs()->activate('history');

        $name = $this->requireObject()->getObjectName();
        $this->addTitle($this->translate('Activity Log: %s'), $name);

        $db = $this->db();
        $type = $this->getType();
        (new ActivityLogTable($db))
            ->setLastDeployedId($db->getLastDeploymentActivityLogId())
            ->filterObject('icinga_' . $type, $name)
            ->renderTo($this);
    }

    public function membershipAction()
    {
        $object = $this->requireObject();
        if (! $object instanceof IcingaObjectGroup) {
            throw new NotFoundError('Not Found');
        }

        $this
            ->addTitle($this->translate('Group membership: %s'), $object->getObjectName())
            ->setAutorefreshInterval(15)
            ->tabs()->activate('membership');

        $type = substr($this->getType(), 0, -5);
        GroupMemberTable::create($type, $this->db())
            ->setGroup($object)
            ->renderTo($this);
    }

    protected function getType()
    {
        // Strip final 's' and upcase an eventual 'group'
        return preg_replace(
            array('/group$/', '/period$/', '/argument$/', '/apiuser$/', '/set$/'),
            array('Group', 'Period', 'Argument', 'ApiUser', 'Set'),
            $this->getRequest()->getControllerName()
        );
    }

    protected function eventuallyLoadObject()
    {
        if (null !== $this->params->get('name') || $this->params->get('id')) {
            $this->loadObject();
        }
    }

    protected function loadObject()
    {
        if ($this->object === null) {
            if ($id = $this->params->get('id')) {
                $this->object = IcingaObject::loadByType(
                    $this->getType(),
                    (int) $id,
                    $this->db()
                );
            } elseif (null !== ($name = $this->params->get('name'))) {
                $this->object = IcingaObject::loadByType(
                    $this->getType(),
                    $name,
                    $this->db()
                );

                if (! $this->allowsObject($this->object)) {
                    $this->object = null;
                    throw new NotFoundError('No such object available');
                }
            } elseif ($this->getRequest()->isApiRequest()) {
                if ($this->getRequest()->isGet()) {
                    $this->getResponse()->setHttpResponseCode(422);

                    throw new InvalidPropertyException(
                        'Cannot load object, missing parameters'
                    );
                }
            }

            if ($this->object !== null) {
                $info = new DeploymentInfo($this->db());
                $info->setObject($this->object);

                if (! $this->getRequest()->isApiRequest()) {
                    $this->actions()->add(
                        DeploymentLinkForm::create($this->db(), $info, $this->Auth(), $this->api())->handleRequest()
                    );
                }
            }
        }

        return $this->object;
    }

    protected function handleApiRequest()
    {
        $response = $this->getResponse();
        try {
            $this->loadObject();
            $this->processApiRequest();
        } catch (NotFoundError $e) {
            $response->setHttpResponseCode(404);
            $this->sendJson($response, (object) ['error' => $e->getMessage()]);
            return;
        } catch (DuplicateKeyException $e) {
            $response->setHttpResponseCode(422);
            $this->sendJson($response, (object) ['error' => $e->getMessage()]);
            return;
        } catch (Exception $e) {
            if ($response->getHttpResponseCode() === 200) {
                $response->setHttpResponseCode(500);
            }

            $this->sendJson($response, (object) ['error' => $e->getMessage()]);
        }

        if ($this->getRequest()->getActionName() !== 'index') {
            throw new NotFoundError('Not found');
        }
    }

    protected function processApiRequest()
    {
        $request = $this->getRequest();
        $db = $this->db();

        switch ($request->getMethod()) {
            case 'DELETE':
                $this->requireObject();
                $obj = $this->object->toPlainObject(false, true);
                $this->loadForm(
                    'icingaDeleteObject'
                )->setObject($this->object)->setRequest($request)->onSuccess();

                $this->sendJson($this->getResponse(), $obj);
                break;

            case 'POST':
            case 'PUT':
                $type = $this->getType();
                $data = json_decode($request->getRawBody());

                if ($data === null) {
                    $this->getResponse()->setHttpResponseCode(400);
                    throw new IcingaException(
                        'Invalid JSON: %s' . $request->getRawBody(),
                        $this->getLastJsonError()
                    );
                } else {
                    $data = (array) $data;
                }
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
                    $status = $object->hasBeenLoadedFromDb() ? 200 : 201;
                    $object->store();
                    $response->setHttpResponseCode($status);
                } else {
                    $response->setHttpResponseCode(304);
                }

                $this->sendJson($response, $object->toPlainObject(false, true));
                break;

            case 'GET':
                $this->requireObject();
                $this->sendJson(
                    $this->getResponse(),
                    $this->object->toPlainObject(
                        $this->params->shift('resolved'),
                        ! $this->params->shift('withNull'),
                        $this->params->shift('properties')
                    )
                );
                break;

            default:
                $request->getResponse()->setHttpResponseCode(400);
                throw new Exception('Unsupported method ' . $request->getMethod());
        }
    }

    protected function countUndeployedChanges()
    {
        if ($this->object === null) {
            return 0;
        }

        return $this->db()->countActivitiesSinceLastDeployedConfig($this->object);
    }

    protected function requireObject()
    {
        if (! $this->object) {
            $this->getResponse()->setHttpResponseCode(404);
            if (null === $this->params->get('name')) {
                throw new NotFoundError('You need to pass a "name" parameter to access a specific object');
            } else {
                throw new NotFoundError('No such object available');
            }
        }

        return $this->object;
    }
}
