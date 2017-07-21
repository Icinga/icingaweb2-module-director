<?php

namespace Icinga\Module\Director\Web\Controller\Extension;

use Exception;
use Icinga\Exception\IcingaException;
use Icinga\Exception\InvalidPropertyException;
use Icinga\Exception\NotFoundError;
use Icinga\Module\Director\Forms\IcingaDeleteObjectForm;
use Icinga\Module\Director\Objects\IcingaObject;
use Icinga\Web\Request;
use Icinga\Web\Response;

class SingleObjectApiHandler
{
    use DirectorDb;

    /** @var IcingaObject */
    private $object;

    /** @var string */
    private $type;

    /** @var Request */
    private $request;

    /** @var Response */
    private $response;

    /** @var \Icinga\Web\UrlParams */
    private $params;

    public function __construct($type, Request $request, Response $response)
    {
        $this->type = $type;
        $this->request = $request;
        $this->response = $response;
        $this->params = $request->getUrl()->getParams();
    }

    public function runFailSafe()
    {
        try {
            $this->loadObject();
            $this->run();
        } catch (NotFoundError $e) {
            $this->sendJsonError($e->getMessage(), 404);
        } catch (Exception $e) {
            $response = $this->response;
            if ($response->getHttpResponseCode() === 200) {
                $response->setHttpResponseCode(500);
            }

            $this->sendJsonError($e->getMessage());
        }
    }

    protected function retrieveObject()
    {
        $this->requireObject();
        $this->sendJson(
            $this->object->toPlainObject(
                $this->params->shift('resolved'),
                ! $this->params->shift('withNull'),
                $this->params->shift('properties')
            )
        );
    }

    protected function deleteObject()
    {
        $this->requireObject();
        $obj = $this->object->toPlainObject(false, true);
        $form = new IcingaDeleteObjectForm();
        $form->setObject($this->object)
            ->setRequest($this->request)
            ->onSuccess();

        $this->sendJson($obj);
    }

    protected function storeObject()
    {
        $data = json_decode($this->request->getRawBody());

        if ($data === null) {
            $this->response->setHttpResponseCode(400);
            throw new IcingaException(
                'Invalid JSON: %s' . $this->request->getRawBody(),
                $this->getLastJsonError()
            );
        } else {
            $data = (array) $data;
        }

        if ($object = $this->object) {
            if ($this->request->getMethod() === 'POST') {
                $object->setProperties($data);
            } else {
                $data = array_merge([
                    'object_type' => $object->object_type,
                    'object_name' => $object->object_name
                ], $data);
                $object->replaceWith(
                    IcingaObject::createByType($this->type, $data, $db)
                );
            }
        } else {
            $object = IcingaObject::createByType($this->type, $data, $db);
        }

        if ($object->hasBeenModified()) {
            $status = $object->hasBeenLoadedFromDb() ? 200 : 201;
            $object->store();
            $this->response->setHttpResponseCode($status);
        } else {
            $this->response->setHttpResponseCode(304);
        }

        $this->sendJson($object->toPlainObject(false, true));
    }

    public function run()
    {
        switch ($this->request->getMethod()) {
            case 'DELETE':
                $this->deleteObject();
                break;

            case 'POST':
            case 'PUT':
                $this->storeObject();
                break;

            case 'GET':
                $this->retrieveObject();
                break;

            default:
                $this->response->setHttpResponseCode(400);
                throw new IcingaException(
                    'Unsupported method: %s',
                    $this->request->getMethod()
                );
        }
    }

    protected function requireObject()
    {
        if (! $this->object) {
            $this->response->setHttpResponseCode(404);
            if (! $this->params->get('name')) {
                throw new NotFoundError('You need to pass a "name" parameter to access a specific object');
            } else {
                throw new NotFoundError('No such object available');
            }
        }
    }

    // TODO: just return json_last_error_msg() for PHP >= 5.5.0
    protected function getLastJsonError()
    {
        switch (json_last_error()) {
            case JSON_ERROR_DEPTH:
                return 'The maximum stack depth has been exceeded';
            case JSON_ERROR_CTRL_CHAR:
                return 'Control character error, possibly incorrectly encoded';
            case JSON_ERROR_STATE_MISMATCH:
                return 'Invalid or malformed JSON';
            case JSON_ERROR_SYNTAX:
                return 'Syntax error';
            case JSON_ERROR_UTF8:
                return 'Malformed UTF-8 characters, possibly incorrectly encoded';
            default:
                return 'An error occured when parsing a JSON string';
        }
    }

    protected function sendJson($object)
    {
        $this->response->setHeader('Content-Type', 'application/json', true);
        $this->_helper->layout()->disableLayout();
        $this->_helper->viewRenderer->setNoRender(true);
        echo json_encode($object, JSON_PRETTY_PRINT) . "\n";
    }

    protected function sendJsonError($message, $code = null)
    {
        $response = $this->response;

        if ($code !== null) {
            $response->setHttpResponseCode((int) $code);
        }

        $this->sendJson((object) ['error' => $message]);
    }

    protected function loadObject()
    {
        if ($this->object === null) {
            if ($name = $this->params->get('name')) {
                $this->object = IcingaObject::loadByType(
                    $this->type,
                    $name,
                    $this->db()
                );

                if (! $this->allowsObject($this->object)) {
                    $this->object = null;
                    throw new NotFoundError('No such object available');
                }
            } elseif ($id = $this->params->get('id')) {
                $this->object = IcingaObject::loadByType(
                    $this->type,
                    (int) $id,
                    $this->db()
                );
            } elseif ($this->request->isApiRequest()) {
                if ($this->request->isGet()) {
                    $this->response->setHttpResponseCode(422);

                    throw new InvalidPropertyException(
                        'Cannot load object, missing parameters'
                    );
                }
            }
        }

        return $this->object;
    }

    protected function allowsObject(IcingaObject $object)
    {
        return true;
    }
}
