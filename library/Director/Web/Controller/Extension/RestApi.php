<?php

namespace Icinga\Module\Director\Web\Controller\Extension;

use Icinga\Exception\AuthenticationException;
use Icinga\Exception\NotFoundError;
use Icinga\Module\Director\Controllers\ServicetemplatesController;
use Icinga\Web\Response;

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

    protected function checkForRestApiRequest()
    {
        if ($this->getRequest()->isApiRequest()) {
            if (! $this->hasPermission('director/api')) {
                throw new AuthenticationException('You are not allowed to access this API');
            }

            if (! $this->isApified()) {
                throw new NotFoundError('No such API endpoint found');
            }
        }
    }

    protected function sendJson(Response $response, $object)
    {
        $response->setHeader('Content-Type', 'application/json', true);
        $this->_helper->layout()->disableLayout();
        $this->_helper->viewRenderer->setNoRender(true);
        /** @var ServicetemplatesController $this */
        $this->viewRenderer->disable();
        echo json_encode($object, JSON_PRETTY_PRINT) . "\n";
    }

    protected function sendJsonError(Response $response, $message, $code = null)
    {
        if ($code !== null) {
            $response->setHttpResponseCode((int) $code);
        }

        $this->sendJson($response, (object) ['error' => $message]);
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
}
