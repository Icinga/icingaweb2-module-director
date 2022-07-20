<?php

namespace Icinga\Module\Director\RestApi;

use Exception;
use gipfl\Json\JsonString;
use Icinga\Module\Director\Db;
use Icinga\Web\Request;
use Icinga\Web\Response;

abstract class RequestHandler
{
    /** @var Request */
    protected $request;

    /** @var Response */
    protected $response;

    /** @var Db */
    protected $db;

    public function __construct(Request $request, Response $response, Db $db)
    {
        $this->request = $request;
        $this->response = $response;
        $this->db = $db;
    }

    abstract protected function processApiRequest();

    public function dispatch()
    {
        $this->processApiRequest();
    }

    public function sendJson($object)
    {
        $this->response->setHeader('Content-Type', 'application/json', true);
        $this->response->sendHeaders();
        echo JsonString::encode($object, JSON_PRETTY_PRINT) . "\n";
    }

    public function sendJsonError($error, $code = null)
    {
        $response = $this->response;
        if ($code === null) {
            if ($response->getHttpResponseCode() === 200) {
                $response->setHttpResponseCode(500);
            }
        } else {
            $response->setHttpResponseCode((int) $code);
        }

        if ($error instanceof Exception) {
            $message = $error->getMessage();
        } else {
            $message = $error;
        }

        $response->sendHeaders();
        $result = ['error' => $message];
        if ($this->request->getUrl()->getParam('showStacktrace')) {
            $result['trace'] = $error->getTraceAsString();
        }
        $this->sendJson((object) $result);
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
