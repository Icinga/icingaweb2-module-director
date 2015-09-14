<?php

namespace Icinga\Module\Director\Core;

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
        $response = array();
        foreach ($this->results as $result) {
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
        if ($result === false) {
            return $this->setJsonError();
        }

        $this->results = $result->results; // TODO: Check if set
        return $this;
    }

    protected function setJsonError()
    {
        switch (json_last_error()) {
            default:
                $this->errorMessage = 'An unknown JSON decode error occured';
        }

        return $this;
    }
}
