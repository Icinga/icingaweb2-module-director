<?php

namespace Icinga\Module\Director\Core;

use Icinga\Exception\IcingaException;
use Icinga\Exception\NotFoundError;

class RestApiResponse
{
    protected $errorMessage;

    protected $results;

    protected function __construct()
    {
    }

    public static function fromJsonResult($json)
    {
        $response = new static;
        return $response->parseJsonResult($json);
    }

    public static function fromErrorMessage($error)
    {
        $response = new static;
        $response->errorMessage = $error;

        return $response;
    }

    public function getResult($desiredKey, $filter = array())
    {
        return $this->extractResult($this->results, $desiredKey, $filter);
    }

    public function getRaw($key = null, $default = null)
    {
        if ($key === null) {
            return $this->results;
        } elseif (isset($this->results[0]) && property_exists($this->results[0], $key)) {
            return $this->results[0]->$key;
        } else {
            return $default;
        }
    }

    public function getSingleResult()
    {
        if ($this->isErrorCode($this->results[0]->code)) {
            throw new IcingaException(
                $this->results[0]->status
            );
        } else {
            return $this->results[0]->result;
        }
    }

    protected function isErrorCode($code)
    {
        $code = (int) ceil($code);
        return $code >= 400;
    }

    protected function extractResult($results, $desiredKey, $filter = array())
    {
        $response = array();
        foreach ($results as $result) {
            foreach ($filter as $key => $val) {
                if (! property_exists($result, $key)) {
                    continue;
                }
                if ($result->$key !== $val) {
                    continue;
                }
            }
            if (! property_exists($result, $desiredKey)) {
                continue;
            }

            $response[$result->$desiredKey] = $result;
        }
        return $response;
    }

    public function getErrorMessage()
    {
        return $this->errorMessage;
    }

    public function succeeded()
    {
        return $this->errorMessage === null;
    }

    protected function parseJsonResult($json)
    {
        $result = @json_decode($json);
        if ($result === null) {
            $this->setJsonError();
            // <h1>Bad Request</h1><p><pre>bad version</pre></p>
            throw new IcingaException(
                'Parsing JSON result failed: '
                . $this->errorMessage
                . ' (Got: ' . substr($json, 0, 60) . ')'
            );
        }
        if (property_exists($result, 'error')) {
            if (property_exists($result, 'status')) {
                if ((int) $result->error === 404) {
                    throw new NotFoundError($result->status);
                } else {
                    throw new IcingaException('API request failed: ' . $result->status);
                }
            } else {
                throw new IcingaException('API request failed: ' . var_export($result, true));
            }
        }

        $this->results = $result->results; // TODO: Check if set
        return $this;
    }

    // TODO: just return json_last_error_msg() for PHP >= 5.5.0
    protected function setJsonError()
    {
        switch (json_last_error()) {
            case JSON_ERROR_DEPTH:
                $this->errorMessage = 'The maximum stack depth has been exceeded';
                break;
            case JSON_ERROR_CTRL_CHAR:
                $this->errorMessage = 'Control character error, possibly incorrectly encoded';
                break;
            case JSON_ERROR_STATE_MISMATCH:
                $this->errorMessage = 'Invalid or malformed JSON';
                break;
            case JSON_ERROR_SYNTAX:
                $this->errorMessage = 'Syntax error';
                break;
            case JSON_ERROR_UTF8:
                $this->errorMessage = 'Malformed UTF-8 characters, possibly incorrectly encoded';
                break;
            default:
                $this->errorMessage = 'An error occured when parsing a JSON string';
        }

        return $this;
    }
}
