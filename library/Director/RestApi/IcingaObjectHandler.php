<?php

namespace Icinga\Module\Director\RestApi;

use Exception;
use Icinga\Exception\IcingaException;
use Icinga\Exception\NotFoundError;
use Icinga\Exception\ProgrammingError;
use Icinga\Module\Director\Core\CoreApi;
use Icinga\Module\Director\Data\Exporter;
use Icinga\Module\Director\Db;
use Icinga\Module\Director\Exception\DuplicateKeyException;
use Icinga\Module\Director\Objects\IcingaObject;

class IcingaObjectHandler extends RequestHandler
{
    /** @var IcingaObject */
    protected $object;

    /** @var CoreApi */
    protected $api;

    /** @var Db */
    protected $connection;

    public function setObject(IcingaObject $object)
    {
        $this->object = $object;
        $this->connection = $object->getConnection();
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
    protected function eventuallyLoadObject()
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
        $response = $this->response;
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
                $type = $this->getType();
                if ($object = $this->eventuallyLoadObject()) {
                    if ($request->getMethod() === 'POST') {
                        $object->setProperties($data);
                    } else {
                        $data = array_merge([
                            'object_type' => $object->get('object_type'),
                            'object_name' => $object->getObjectName()
                        ], $data);
                        $object->replaceWith(
                            IcingaObject::createByType($type, $data, $db)
                        );
                    }
                } else {
                    $object = IcingaObject::createByType($type, $data, $db);
                }

                if ($object->hasBeenModified()) {
                    $status = $object->hasBeenLoadedFromDb() ? 200 : 201;
                    $object->store();
                    $response->setHttpResponseCode($status);
                } else {
                    $response->setHttpResponseCode(304);
                }

                $this->sendJson($object->toPlainObject(false, true));
                break;

            case 'GET':
                $object = $this->requireObject();
                $exporter = new Exporter($this->connection);
                RestApiParams::applyParamsToExporter($exporter, $this->request, $object->getShortTableName());
                $this->sendJson($exporter->export($object));
                break;

            default:
                $request->getResponse()->setHttpResponseCode(400);
                throw new IcingaException('Unsupported method ' . $request->getMethod());
        }
    }
}
