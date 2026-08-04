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
use Icinga\Module\Director\Exception\NestingError;
use Icinga\Module\Director\Objects\IcingaHost;
use Icinga\Module\Director\Objects\IcingaObject;
use Icinga\Module\Director\Resolver\OverrideHelper;
use InvalidArgumentException;
use RuntimeException;
use stdClass;

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

        static::assertJsonBodyIsObject($data);

        return $data;
    }

    protected function getType()
    {
        return $this->request->getControllerName();
    }

    /**
     * Assert that DELETE is being used on the object endpoint itself
     *
     * DELETE is not defined for the variables sub resource, only for the
     * whole object.
     *
     * @param string $actionName
     *
     * @throws NotFoundError
     */
    public static function assertDeleteAllowed(string $actionName): void
    {
        if ($actionName !== 'index') {
            throw new NotFoundError('Not found');
        }
    }

    /**
     * Assert that a decoded JSON body is a JSON object
     *
     * The REST API always expects a map of property names to values at the
     * top level, never a plain JSON array. This throws InvalidArgumentException,
     * not IcingaException, so that processApiRequest() maps it to HTTP 422
     * the same way it maps every other malformed override.
     *
     * @param mixed $decoded
     *
     * @throws InvalidArgumentException
     */
    public static function assertJsonBodyIsObject(mixed $decoded): void
    {
        if (! $decoded instanceof stdClass) {
            throw new InvalidArgumentException(sprintf(
                'Invalid JSON body, expected a JSON object, got %s',
                get_debug_type($decoded)
            ));
        }
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
        } catch (InvalidArgumentException $e) {
            $this->sendJsonError($e, 422);
            return;
        } catch (Exception $e) {
            $this->sendJsonError($e);
        }

        if ($this->request->getActionName() !== 'index' && $this->request->getActionName() !== 'variables') {
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
                static::assertDeleteAllowed($this->request->getActionName());
                $object = $this->requireObject();
                $object->delete();
                $this->sendJson($object->toPlainObject(false, true));

                break;
            case 'POST':
            case 'PUT':
                $data = (array) $this->requireJsonBody();
                $params = $this->request->getUrl()->getParams();
                $allowsOverrides = $params->get('allowOverrides', false);
                $type = $this->getType();
                $object = $this->loadOptionalObject();
                $actionName = $this->request->getActionName();
                $method = $request->getMethod();

                $overRiddenCustomVars = [];
                $replaceAll = false;
                if ($actionName === 'variables') {
                    if ($object === null) {
                        throw new InvalidArgumentException(
                            'Cannot set variables, no matching object was found. Please provide a valid '
                            . '"name" (and "host" for services), "uuid" or "id" parameter.'
                        );
                    }

                    $overRiddenCustomVars = $data;
                } else {
                    // Extract custom vars from the data
                    if (isset($data['vars'])) {
                        $overRiddenCustomVars = (array) $data['vars'];
                        $replaceAll = $method === 'POST';

                        unset($data['vars']);
                    }

                    foreach ($data as $key => $value) {
                        if (substr($key, 0, 5) === 'vars.') {
                            $overRiddenCustomVars[substr($key, 5)] = $value;

                            unset($data[$key]);
                        }
                    }
                }

                $writeRequest = new IcingaObjectWriteRequest(
                    $object,
                    $data,
                    $type,
                    $actionName,
                    $method,
                    $replaceAll,
                    $overRiddenCustomVars,
                    $allowsOverrides,
                    $params
                );

                // Object persistence and custom-variable validation/application must
                // succeed or fail together: without this, a request with an invalid
                // custom variable could still leave unrelated object changes committed.
                // persistObjectAndApplyVars() returns null once it has already sent its
                // own response (the service-override branch), or the object to respond
                // with otherwise.
                $responseObject = null;
                $db->runFailSafeTransaction(function () use (&$responseObject, $writeRequest) {
                    $responseObject = $this->persistObjectAndApplyVars($writeRequest);
                });

                if ($responseObject !== null) {
                    $this->sendJson($responseObject->toPlainObject(false, true));
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

    /**
     * Persist an object's own property changes and apply its custom-variable
     * overrides as a single unit of work
     *
     * Called from within a transaction (see handleApiRequest()): a failure in the
     * custom-variable step must not leave the object's own property changes
     * committed on their own.
     *
     * @return ?IcingaObject The object to respond with, or null if a response has
     *                       already been sent (the service-override branch)
     */
    protected function persistObjectAndApplyVars(IcingaObjectWriteRequest $request): ?IcingaObject
    {
        $db = $this->db;
        $object = $request->object;
        $data = $request->data;
        $type = $request->type;

        if ($request->actionName !== 'variables') {
            if ($object) {
                if ($request->method === 'POST') {
                    $object->setProperties($data);
                } else {
                    $data = array_merge([
                        'object_type' => $object->get('object_type'),
                        'object_name' => $object->getObjectName()
                    ], $data);
                    $object->replaceWith(IcingaObject::createByType($type, $data, $db));
                }

                // Avoid cyclic imports for hosts and commands. Checked against the
                // in-memory imports set above, so a cycle introduced by this very
                // request is caught before it gets persisted.
                if (in_array($object->getShortTableName(), ['host', 'command'], true)) {
                    // listAncestorIds() throws NestingError as soon as it hits a loop,
                    // so an indirect cycle never reaches the check below. Wrap it into
                    // the same exception a direct self-import already gets.
                    try {
                        $isOwnAncestor = in_array((int) $object->get('id'), $object->listAncestorIds());
                    } catch (NestingError $e) {
                        throw new RuntimeException(
                            'Import loop detected for the object '
                            . $object->getObjectName() . ': ' . $e->getMessage()
                        );
                    }

                    if ($isOwnAncestor) {
                        throw new RuntimeException(
                            'Import loop detected for the object '
                            . $object->getObjectName() . ' -> Imports: '
                            . implode(', ', $object->getImports())
                        );
                    }

                    if (in_array($object->getObjectName(), $object->getImports())) {
                        throw new RuntimeException(
                            'You can not import the same object into itself: ' . $object->getObjectName()
                        );
                    }
                }

                $this->persistChanges($object);
            } elseif ($request->allowsOverrides && $type === 'service') {
                if ($request->method === 'PUT') {
                    throw new InvalidArgumentException('Overrides are not (yet) available for HTTP PUT');
                }

                $data['vars'] = $request->overRiddenCustomVars;
                $this->setServiceProperties(
                    $request->params->getRequired('host'),
                    $request->params->getRequired('name'),
                    $data
                );

                return null;
            } else {
                $object = IcingaObject::createByType($type, $data, $db);
                $this->persistChanges($object);
            }
        }

        $isVariablesPut = $request->actionName === 'variables' && $request->method === 'PUT';
        if (empty($request->overRiddenCustomVars) && ! $isVariablesPut && ! $request->replaceAll) {
            return $object;
        }

        if (! $object->hasBeenLoadedFromDb()) {
            // object was never actually created (e.g. a vars-only body with no
            // properties to create it from), so there is nothing to attach vars to
            throw new InvalidArgumentException(
                'Cannot set variables, the object was not created. Provide the properties'
                . ' required to create it along with the variable overrides.'
            );
        }

        $varsWereChanged = (new CustomVariableValueApplier($db))->apply(new CustomVarApplyRequest(
            $object,
            $request->overRiddenCustomVars,
            $request->actionName,
            $request->method,
            $request->replaceAll
        ));

        // persistChanges() only checks the object's own columns, so it may have
        // already set 304 even though the applier above really changed the
        // database. Fix that up without touching a 201 already earned.
        if ($varsWereChanged && $this->response->getHttpResponseCode() === 304) {
            $this->response->setHttpResponseCode(200);
        }

        return $object::requireWithUniqueId($object->getUniqueId(), $db);
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
