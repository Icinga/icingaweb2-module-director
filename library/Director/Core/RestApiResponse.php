<?php

namespace Icinga\Module\Director\Core;

use Icinga\Exception\IcingaException;

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

    public function getSingleResult()
    {
        return $this->results[0]->result;
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
            throw new IcingaException('Parsing JSON result failed: ' . $this->errorMessage);
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
