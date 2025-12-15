<?php

namespace Icinga\Module\Director\RestApi;

use Exception;
use Icinga\Exception\IcingaException;
use Icinga\Exception\NotFoundError;
use Icinga\Exception\ProgrammingError;
use Icinga\Module\Director\Core\CoreApi;
use Icinga\Module\Director\CustomVariable\CustomVariables;
use Icinga\Module\Director\Data\Exporter;
use Icinga\Module\Director\DirectorObject\Lookup\ServiceFinder;
use Icinga\Module\Director\Exception\DuplicateKeyException;
use Icinga\Module\Director\Objects\IcingaHost;
use Icinga\Module\Director\Objects\IcingaObject;
use Icinga\Module\Director\Resolver\OverrideHelper;
use InvalidArgumentException;
use PDO;
use Ramsey\Uuid\Uuid;
use RuntimeException;

class IcingaObjectHandler extends RequestHandler
{
    /** @var IcingaObject */
    protected $object;

    /** @var CoreApi */
    protected $api;

    public function setObject(IcingaObject $object)
    {
        $this->object = $object;
        return $this;
    }

    public function setApi(CoreApi $api)
    {
        $this->api = $api;
        return $this;
    }

    /**
     * @return IcingaObject
     * @throws ProgrammingError
     */
    protected function requireObject()
    {
        if ($this->object === null) {
            throw new ProgrammingError('Object is required');
        }

        return $this->object;
    }

    /**
     * @return IcingaObject
     */
    protected function loadOptionalObject()
    {
        return $this->object;
    }

    protected function requireJsonBody()
    {
        $data = json_decode($this->request->getRawBody());

        if ($data === null) {
            $this->response->setHttpResponseCode(400);
            throw new IcingaException(
                'Invalid JSON: %s',
                $this->getLastJsonError()
            );
        }

        return $data;
    }

    protected function getType()
    {
        return $this->request->getControllerName();
    }

    protected function processApiRequest()
    {
        try {
            $this->handleApiRequest();
        } catch (NotFoundError $e) {
            $this->sendJsonError($e, 404);
            return;
        } catch (DuplicateKeyException $e) {
            $this->sendJsonError($e, 422);
            return;
        } catch (Exception $e) {
            $this->sendJsonError($e);
        }

        if ($this->request->getActionName() !== 'index' && $this->request->getActionName() !== 'variables') {
            throw new NotFoundError('Not found');
        }
    }

    /**
     * Get the custom properties linked to the given object.
     *
     * @param IcingaObject $object
     *
     * @return array
     */
    public function getCustomProperties(IcingaObject $object): array
    {
        if ($object->get('uuid') === null) {
            return [];
        }

        $type = $object->getShortTableName();
        $db = $object->getConnection();
        $ids = $object->listAncestorIds();
        $ids[] = $object->get('id');
        $query = $db->getDbAdapter()
                    ->select()
                    ->from(
                        ['dp' => 'director_property'],
                        [
                            'key_name' => 'dp.key_name',
                            'uuid' => 'dp.uuid',
                            'value_type' => 'dp.value_type',
                            'label' => 'dp.label'
                        ]
                    )
                    ->join(['iop' => "icinga_$type" . '_property'], 'dp.uuid = iop.property_uuid', [])
                    ->join(['io' => "icinga_$type"], 'io.uuid = iop.' . $type . '_uuid', [])
                    ->where('io.id IN (?)', $ids)
                    ->group(['dp.uuid', 'dp.key_name', 'dp.value_type', 'dp.label'])
                    ->order('key_name');

        $result = [];
        foreach ($db->getDbAdapter()->fetchAll($query, fetchMode: PDO::FETCH_ASSOC) as $row) {
            $result[$row['key_name']] = $row;
        }

        return $result;
    }

    protected function handleApiRequest()
    {
        $request = $this->request;
        $db = $this->db;

        // TODO: I hate doing this:
        if ($this->request->getActionName() === 'ticket') {
            $host = $this->requireObject();

            if ($host->getResolvedProperty('has_agent') !== 'y') {
                throw new NotFoundError('The host "%s" is not an agent', $host->getObjectName());
            }

            $this->sendJson($this->api->getTicket($host->getObjectName()));

            // TODO: find a better way to shut down. Currently, this avoids
            //       "not found" errors:
            exit;
        }

        switch ($request->getMethod()) {
            case 'DELETE':
                $object = $this->requireObject();
                $object->delete();
                $this->sendJson($object->toPlainObject(false, true));

                break;
            case 'POST':
            case 'PUT':
                $data = (array) $this->requireJsonBody();
                $params = $this->request->getUrl()->getParams();
                $allowsOverrides = $params->get('allowOverrides');
                $type = $this->getType();
                $object = $this->loadOptionalObject();
                $actionName = $this->request->getActionName();

                $overRiddenCustomVars = [];
                if ($actionName === 'variables') {
                    $overRiddenCustomVars = $data;
                } else {
                    // TODO: Remove this if condition once the custom vars are implemented for other objects
                    if ($type === 'host') {
                        // Extract custom vars from the data
                        foreach ($data as $key => $value) {
                            if ($key === 'vars') {
                                $overRiddenCustomVars = ['vars' => (array) $value];

                                unset($data['vars']);
                            }

                            if (substr($key, 0, 5) === 'vars.') {
                                $overRiddenCustomVars['vars'][substr($key, 5)] = $value;

                                unset($data[$key]);
                            }
                        }
                    }

                    if ($object) {
                        if ($request->getMethod() === 'POST') {
                            $object->setProperties($data);
                        } else {
                            $data = array_merge([
                                'object_type' => $object->get('object_type'),
                                'object_name' => $object->getObjectName()
                            ], $data);
                            $object->replaceWith(IcingaObject::createByType($type, $data, $db));
                        }

                        $this->persistChanges($object);
                    } elseif ($allowsOverrides && $type === 'service') {
                        if ($request->getMethod() === 'PUT') {
                            throw new InvalidArgumentException('Overrides are not (yet) available for HTTP PUT');
                        }

                        $this->setServiceProperties($params->getRequired('host'), $params->getRequired('name'), $data);
                    } else {
                        $object = IcingaObject::createByType($type, $data, $db);
                        $this->persistChanges($object);
                    }
                }

                if ($type === 'service' || empty($overRiddenCustomVars)) {
                    $this->sendJson($object->toPlainObject(false, true));

                    break;
                }

                $objectVars = $object->vars();
                if ($request->getMethod() === 'PUT') {
                    $objectWhere = $db->getDbAdapter()->quoteInto('host_id = ?', $this->object->get('id'));
                    $db->getDbAdapter()->delete(
                        'icinga_' . $type . '_var',
                        $objectWhere
                    );

                    $objectPropertyWhere = $db->getDbAdapter()->quoteInto('host_uuid = ?', Uuid::fromBytes($this->object->get('uuid'))->getBytes());
                    $db->getDbAdapter()->delete(
                        'icinga_' . $type . '_property',
                        $objectPropertyWhere
                    );

                    $objectVars = new CustomVariables();
                }

                $customProperties = $this->getCustomProperties($object);

                foreach ($overRiddenCustomVars as $key => $value) {
                    if (isset($customProperties[$key])) {
                        $objectVars->registerVarUuid($key, Uuid::fromBytes($customProperties[$key]['uuid']));
                        $objectVars->set($key, $value);
                        $objectVars->get($key)->setModified();

                        continue;
                    }

                    if (! $object->isTemplate()) {
                        throw new NotFoundError(sprintf(
                            "The custom property %s should be first added to one of the imported templates"
                            . " for this object",
                            $key
                        ));
                    }

                    if ($request->getMethod() === 'POST') {
                        $errMsg = sprintf(
                            "The custom property %s should be first added to the template",
                            $key
                        );

                        throw new NotFoundError($errMsg);
                    }

                    $query = $db->getDbAdapter()
                                ->select()
                                ->from(
                                    ['dp' => 'director_property'],
                                    [
                                        'key_name' => 'dp.key_name',
                                        'uuid' => 'dp.uuid',
                                        'value_type' => 'dp.value_type',
                                        'label' => 'dp.label'
                                    ]
                                )
                                ->where('dp.key_name = ? AND dp.parent_uuid IS NULL', $key);
                    $customProperty = $db->getDbAdapter()->fetchRow($query, [], PDO::FETCH_ASSOC);

                    if (! $customProperty) {
                        throw new NotFoundError(sprintf(
                            "'%s' of value type '%s' is not configured in Icinga Director as a custom property",
                            $key,
                            $customProperty->value_type
                        ));
                    }

                    if (! isset($customProperties[$key])) {
                        $db->getDbAdapter()->insert(
                            'icinga_' . $type . '_property',
                            [
                                'property_uuid' => $customProperty['uuid'],
                                $type . '_uuid' => $object->get('uuid')
                            ]
                        );
                    }

                    $objectVars->registerVarUuid($key, Uuid::fromBytes($customProperty['uuid']));
                    $objectVars->set($key, $value);
                }

                $objectVars->storeToDb($object);
                $object = IcingaObject::loadByType($type, $object->getObjectName(), $db);
                $this->sendJson($object->toPlainObject(false, true));

                break;
            case 'GET':
                $object = $this->requireObject();
                $exporter = new Exporter($this->db);
                RestApiParams::applyParamsToExporter($exporter, $this->request, $object->getShortTableName());
                $this->sendJson($exporter->export($object));

                break;
            default:
                $request->getResponse()->setHttpResponseCode(400);
                throw new IcingaException('Unsupported method ' . $request->getMethod());
        }
    }

    protected function persistChanges(IcingaObject $object)
    {
        if ($object->hasBeenModified()) {
            $status = $object->hasBeenLoadedFromDb() ? 200 : 201;
            $object->store();
            $this->response->setHttpResponseCode($status);
        } else {
            $this->response->setHttpResponseCode(304);
        }
    }

    protected function setServiceProperties($hostname, $serviceName, $properties)
    {
        $host = IcingaHost::load($hostname, $this->db);
        $service = ServiceFinder::find($host, $serviceName);
        if ($service === false) {
            throw new NotFoundError('Not found');
        }
        if ($service->requiresOverrides()) {
            unset($properties['host']);
            OverrideHelper::applyOverriddenVars($host, $serviceName, $properties);
            $this->persistChanges($host);
            $this->sendJson($host->toPlainObject(false, true));
        } else {
            throw new RuntimeException('Found a single service, which should have been found (and dealt with) before');
        }
    }
}
