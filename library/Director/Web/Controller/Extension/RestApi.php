<?php

namespace Icinga\Module\Director\Web\Controller\Extension;

use Icinga\Exception\AuthenticationException;
use Icinga\Exception\NotFoundError;
use Icinga\Module\Director\Auth\Permission;
use Icinga\Module\Director\Exception\JsonException;
use Icinga\Web\Response;
use InvalidArgumentException;
use Zend_Controller_Response_Exception;

trait RestApi
{
    protected function isApified()
    {
        if (property_exists($this, 'isApified')) {
            return $this->isApified;
        } else {
            return false;
        }
    }

    /**
     * @return bool
     */
    protected function sendNotFoundForRestApi()
    {
        /** @var \Icinga\Web\Request $request */
        $request = $this->getRequest();
        if ($request->isApiRequest()) {
            $this->sendJsonError($this->getResponse(), 'Not found', 404);
            return true;
        } else {
            return false;
        }
    }

    /**
     * @return bool
     */
    protected function sendNotFoundUnlessRestApi()
    {
        /** @var \Icinga\Web\Request $request */
        $request = $this->getRequest();
        if ($request->isApiRequest()) {
            return false;
        } else {
            $this->sendJsonError($this->getResponse(), 'Not found', 404);
            return true;
        }
    }

    /**
     * @throws AuthenticationException
     */
    protected function assertApiPermission()
    {
        if (! $this->hasPermission(Permission::API)) {
            throw new AuthenticationException('You are not allowed to access this API');
        }
    }

    /**
     * @throws AuthenticationException
     * @throws NotFoundError
     */
    protected function checkForRestApiRequest()
    {
        /** @var \Icinga\Web\Request $request */
        $request = $this->getRequest();
        if ($request->isApiRequest()) {
            $this->assertApiPermission();
            if (! $this->isApified()) {
                throw new NotFoundError('No such API endpoint found');
            }
        }
    }

    /**
     * @param Response $response
     * @param $object
     */
    protected function sendJson(Response $response, $object)
    {
        $response->setHeader('Content-Type', 'application/json', true);
        echo json_encode($object, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
    }

    /**
     * @param Response $response
     * @param string $message
     * @param int|null $code
     */
    protected function sendJsonError(Response $response, $message, $code = null)
    {
        if ($code !== null) {
            try {
                $response->setHttpResponseCode((int) $code);
            } catch (Zend_Controller_Response_Exception $e) {
                throw new InvalidArgumentException($e->getMessage(), 0, $e);
            }
        }

        $this->sendJson($response, (object) ['error' => $message]);
    }

    /**
     * @return string
     */
    protected function getLastJsonError()
    {
        return JsonException::getJsonErrorMessage(json_last_error());
    }
}
