<?php

namespace Icinga\Module\Director\RestApi;

use Exception;
use Icinga\Exception\IcingaException;
use Icinga\Exception\NotFoundError;
use Icinga\Exception\ProgrammingError;
use Icinga\Module\Director\Core\CoreApi;
use Icinga\Module\Director\Data\Exporter;
use Icinga\Module\Director\DirectorObject\Lookup\ServiceFinder;
use Icinga\Module\Director\Exception\DuplicateKeyException;
use Icinga\Module\Director\Objects\IcingaHost;
use Icinga\Module\Director\Objects\IcingaObject;
use Icinga\Module\Director\Resolver\OverrideHelper;
use InvalidArgumentException;
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

        if ($this->request->getActionName() !== 'index') {
            throw new NotFoundError('Not found');
        }
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
                if ($object = $this->loadOptionalObject()) {
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
                    $this->sendJson($object->toPlainObject(false, true));
                } elseif ($allowsOverrides && $type === 'service') {
                    if ($request->getMethod() === 'PUT') {
                        throw new InvalidArgumentException('Overrides are not (yet) available for HTTP PUT');
                    }
                    $this->setServiceProperties($params->getRequired('host'), $params->getRequired('name'), $data);
                } else {
                    $this->persistChanges($object);
                    $object = IcingaObject::createByType($type, $data, $db);
                    $this->sendJson($object->toPlainObject(false, true));
                }

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
