<?php

namespace Icinga\Module\Director\Web\Controller;

use Exception;
use Icinga\Exception\IcingaException;
use Icinga\Exception\InvalidPropertyException;
use Icinga\Exception\NotFoundError;
use Icinga\Module\Director\Exception\DuplicateKeyException;
use Icinga\Module\Director\Exception\NestingError;
use Icinga\Module\Director\Forms\IcingaObjectFieldForm;
use Icinga\Module\Director\Objects\IcingaObject;
use Icinga\Module\Director\Web\Controller\Extension\ObjectRestrictions;
use Icinga\Module\Director\Web\Form\DirectorObjectForm;
use Icinga\Module\Director\Web\Tabs\ObjectTabs;
use ipl\Html\Html;
use ipl\Html\Link;

abstract class ObjectController extends ActionController
{
    use ObjectRestrictions;

    /** @var IcingaObject */
    protected $object;

    protected $isApified = true;

    protected $allowedExternals = array(
        'apiuser',
        'endpoint'
    );

    public function init()
    {
        parent::init();

        if ($this->getRequest()->isApiRequest()) {
            $response = $this->getResponse();
            try {
                $this->loadObject();
                $this->handleApiRequest();
            } catch (NotFoundError $e) {
                $response->setHttpResponseCode(404);
                $this->sendJson($response, (object) array('error' => $e->getMessage()));
            } catch (DuplicateKeyException $e) {
                $response->setHttpResponseCode(422);
                $this->sendJson($response, (object) array('error' => $e->getMessage()));
            } catch (Exception $e) {
                if ($response->getHttpResponseCode() === 200) {
                    $response->setHttpResponseCode(500);
                }

                $this->sendJson($response, (object) array('error' => $e->getMessage()));
            }
        }

        $type = strtolower($this->getType());
        if ($name = $this->params->get('name')) {
            $this->loadObject();
        }
        $this->tabs(new ObjectTabs($type, $this->getAuth(), $this->object));
    }

    public function indexAction()
    {
        if ($this->getRequest()->isApiRequest()) {
            return;
        }

        if ($this->object
            && $this->object->isExternal()
            && ! in_array($this->object->getShortTableName(), $this->allowedExternals)
        ) {
            $this->redirectNow(
                $this->getRequest()->getUrl()->setPath(sprintf('director/%s/render', $this->getType()))
            );
        }

        $this->editAction();
    }

    public function renderAction()
    {
        $this->assertPermission('director/showconfig');
        $this->tabs()->activate('render');
        $object = $this->object;
        $this->addTitle(
            $this->translate('Config preview: %s'),
            $object->object_name
        );

        if ($this->params->shift('resolved')) {
            $object = $object::fromPlainObject(
                $object->toPlainObject(true),
                $object->getConnection()
            );

            $this->actions()->add(Link::create(
                $this->translate('Show normal'),
                $this->getRequest()->getUrl()->without('resolved'),
                null,
                ['class' => 'icon-resize-small state-warning']
            ));
        } else {
            try {
                if ($object->supportsImports() && $object->imports()->count() > 0) {
                    $this->actions()->add(Link::create(
                        $this->translate('Show resolved'),
                        $this->getRequest()->getUrl()->with('resolved', true),
                        null,
                        ['class' => 'icon-resize-full']
                    ));
                }
            } catch (NestingError $e) {
                // No resolve link with nesting errors
            }
        }

        $content = $this->content();
        if ($object->isDisabled()) {
            $content->add(Html::p(
                ['class' => 'error'],
                $this->translate('This object will not be deployed as it has been disabled')
            ));
        }
        if ($object->isExternal()) {
            $content->add(Html::p($this->translate((
                'This is an external object. It has been imported from Icinga 2 throught the'
                . ' Core API and cannot be managed with the Icinga Director. It is however'
                . ' perfectly valid to create objects using this or referring to this object.'
                . ' You might also want to define related Fields to make work based on this'
                . ' object more enjoyable'
            ))));
        }
        $config = $object->toSingleIcingaConfig();

        foreach ($config->getFiles() as $filename => $file) {
            if (! $object->isExternal()) {
                $content->add(Html::h2($filename));
            }

            $classes = array();
            if ($object->isDisabled()) {
                $classes[] = 'disabled';
            } elseif ($object->isExternal()) {
                $classes[] = 'logfile';
            }

            $content->add(Html::pre(['class' => $classes], $file->getContent()));
        }
    }

    public function editAction()
    {
        $object = $this->object;
        $this->addTitle($object->object_name);
        $this->tabs()->activate('modify');

        $formName = 'icinga' . ucfirst($this->getType());
        $this->content()->add(
            $form = $this->loadForm($formName)
                ->setDb($this->db())
                ->setAuth($this->Auth())
                ->setApi($this->getApiIfAvailable())
                ->setObject($object)
                ->setAuth($this->Auth())
                ->handleRequest()
        );

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
        $this->tabs()->activate('add');
        $type = $this->getType();
        $ltype = strtolower($type);

        $url = sprintf('director/%ss', $ltype);
        /** @var DirectorObjectForm $form */
        $form = $this->view->form = $this->loadForm('icinga' . ucfirst($type))
            ->setDb($this->db())
            ->setAuth($this->Auth())
            ->presetImports($this->params->shift('imports'))
            ->setApi($this->getApiIfAvailable())
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
            $this->assertPermission('director/' . $ltype);
            $this->addTitle(
                $this->translate('Add new Icinga %s'),
                ucfirst($ltype)
            );
        }

        $this->beforeHandlingAddRequest($form);
        $form->handleRequest();
        $this->content()->add($form);
    }

    protected function beforeHandlingAddRequest($form)
    {
    }

    public function cloneAction()
    {
        $type = $this->getType();
        $ltype = strtolower($type);
        $this->assertPermission('director/' . $ltype);
        $this->tabs()->activate('modify');
        $this->addTitle($this->translate('Clone Icinga %s'), ucfirst($type));
        $form = $this->loadForm('icingaCloneObject')->setObject($this->object);
        $form->handleRequest();
        $this->content()->add($form);
        $this->actions()->add(Link::create(
            $this->translate('back'),
            'director/' . $ltype,
            array('name'  => $this->object->object_name),
            array('class' => 'icon-left-big')
        ));
    }

    public function fieldsAction()
    {
        $this->hasPermission('director/admin');
        $object = $this->object;
        $type = $this->getType();

        $this->tabs()->activate('fields');

        $this->addTitle(
            $this->translate('Custom fields: %s'),
            $object->object_name
        );

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
        $this->hasPermission('director/audit');
        $this->setAutorefreshInterval(10);
        $db = $this->db();
        $type = $this->getType();
        $this->tabs()->activate('history');
        $this->addTitle(
            $this->translate('Activity Log: %s'),
            $this->object->object_name
        );
        $lastDeployedId = $db->getLastDeploymentActivityLogId();
        $this->content()->add(
            $this->applyPaginationLimits(
                $this->loadTable('activityLog')
                    ->setConnection($db)
                    ->setLastDeployedId($lastDeployedId)
                    ->filterObject('icinga_' . $type, $this->object->object_name)
            )
        )->addAttributes(['data-base-target' => '_next']);
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

    protected function loadObject()
    {
        if ($this->object === null) {
            if ($name = $this->params->get('name')) {
                $this->object = IcingaObject::loadByType(
                    $this->getType(),
                    $name,
                    $this->db()
                );

                if (! $this->allowsObject($this->object)) {
                    $this->object = null;
                    throw new NotFoundError('No such object available');
                }
            } elseif ($id = $this->params->get('id')) {
                $this->object = IcingaObject::loadByType(
                    $this->getType(),
                    (int) $id,
                    $this->db()
                );
            } elseif ($this->getRequest()->isApiRequest()) {
                if ($this->getRequest()->isGet()) {
                    $this->getResponse()->setHttpResponseCode(422);

                    throw new InvalidPropertyException(
                        'Cannot load object, missing parameters'
                    );
                }
            }

            $this->view->undeployedChanges = $this->countUndeployedChanges();
            $this->view->totalUndeployedChanges = $this->db()
                ->countActivitiesSinceLastDeployedConfig();
        }

        return $this->object;
    }

    protected function hasFields()
    {
        if (! ($object = $this->object)) {
            return false;
        }

        return $object->hasBeenLoadedFromDb()
            && $object->supportsFields()
            && ($object->isTemplate() || $this->getType() === 'command');
    }

    protected function handleApiRequest()
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
            if (! $this->params->get('name')) {
                throw new NotFoundError('You need to pass a "name" parameter to access a specific object');
            } else {
                throw new NotFoundError('No such object available');
            }
        }
    }

    protected function gracefullyActivateTab($name)
    {
        $tabs = $this->getTabs();

        if ($tabs->has($name)) {
            return $tabs->activate($name);
        }

        $req = $this->getRequest();
        $this->redirectNow(
            $req->getUrl()->setPath('director/' . $req->getControllerName())
        );
    }

    protected function beforeTabs()
    {
    }
}
