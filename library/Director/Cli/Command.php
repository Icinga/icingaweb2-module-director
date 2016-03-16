<?php

namespace Icinga\Module\Director\Cli;

use Icinga\Cli\Command as CliCommand;
use Icinga\Module\Director\Db;
use Icinga\Module\Director\Objects\IcingaEndpoint;

class Command extends CliCommand
{
    protected $db;

    private $api;

    protected function renderJson($object, $pretty = true)
    {
        if ($pretty && version_compare(PHP_VERSION, '5.4.0') >= 0) {
            return json_encode($object, JSON_PRETTY_PRINT) . "\n";
        } else {
            return json_encode($object) . "\n";
        }
    }

    protected function parseJson($json)
    {
        $res = json_decode($json);

        if ($res === null) {
            $this->fail(sprintf(
                'Invalid JSON',
                $this->getLastJsonError()
            ));
        }

        return $res;
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

        return $this;
    }

    protected function api($endpointName = null)
    {
        if ($this->api === null) {
            if ($endpointName === null) {
                $endpoint = $this->db()->getDeploymentEndpoint();
            } else {
                $endpoint = IcingaEndpoint::load($endpointName, $this->db());
            }

            $this->api = $endpoint->api();
        }

        return $this->api;
    }

    protected function db()
    {
        if ($this->db === null) {
            $resourceName = $this->Config()->get('db', 'resource');
            if ($resourceName) {
                $this->db = Db::fromResourceName($resourceName);
            } else {
                $this->fail('Director is not configured correctly');
            }
        }

        return $this->db;
    }
}
