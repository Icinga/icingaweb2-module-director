<?php

namespace Icinga\Module\Director\Cli;

use gipfl\Json\JsonDecodeException;
use gipfl\Json\JsonString;
use Icinga\Cli\Command as CliCommand;
use Icinga\Module\Director\Application\MemoryLimit;
use Icinga\Module\Director\Core\CoreApi;
use Icinga\Module\Director\Db;
use Icinga\Module\Director\Objects\IcingaEndpoint;
use Icinga\Application\Config;
use RuntimeException;

class Command extends CliCommand
{
    /** @var  Db */
    protected $db;

    /** @var  CoreApi */
    private $api;

    protected function renderJson($object, $pretty = true)
    {
        return JsonString::encode($object, $pretty ? JSON_PRETTY_PRINT : null) . "\n";
    }

    /**
     * @param $json
     * @return mixed
     */
    protected function parseJson($json)
    {
        try {
            return JsonString::decode($json);
        } catch (JsonDecodeException $e) {
            $this->fail('Invalid JSON: %s', $e->getMessage());
        }
    }

    public function fail($msg)
    {
        $args = func_get_args();
        array_shift($args);
        if (count($args)) {
            $msg = vsprintf($msg, $args);
        }

        throw new RuntimeException($msg);
    }

    /**
     * @param null $endpointName
     * @return CoreApi|\Icinga\Module\Director\Core\LegacyDeploymentApi
     * @throws \Icinga\Exception\NotFoundError
     */
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

    /**
     * Raise PHP resource limits
     *
     * @return self;
     */
    protected function raiseLimits()
    {
        MemoryLimit::raiseTo('1024M');

        ini_set('max_execution_time', 0);
        if (version_compare(PHP_VERSION, '7.0.0') < 0) {
            ini_set('zend.enable_gc', 0);
        }

        return $this;
    }

    /**
     * @return Db
     */
    protected function db()
    {
        if ($this->db === null) {
            $resourceName = $this->params->get('dbResourceName');

            if ($resourceName === null) {
                // Hint: not using $this->Config() intentionally. This allows
                // CLI commands in other modules to use this as a base class.
                $resourceName = Config::module('director')->get('db', 'resource');
            }
            if ($resourceName) {
                $this->db = Db::fromResourceName($resourceName);
            } else {
                throw new RuntimeException('Director is not configured correctly');
            }
        }

        return $this->db;
    }
}
