<?php

namespace Icinga\Module\Director\Objects;

use Icinga\Module\Director\Core\CoreApi;
use Icinga\Module\Director\Core\LegacyDeploymentApi;
use Icinga\Module\Director\Core\RestApiClient;
use Icinga\Module\Director\Exception\NestingError;
use Icinga\Module\Director\IcingaConfig\IcingaConfig;
use InvalidArgumentException;
use RuntimeException;

class IcingaEndpoint extends IcingaObject
{
    protected $table = 'icinga_endpoint';

    protected $supportsImports = true;

    protected $uuidColumn = 'uuid';

    protected $defaultProperties = [
        'id'           => null,
        'uuid'         => null,
        'zone_id'      => null,
        'object_name'  => null,
        'object_type'  => null,
        'disabled'     => 'n',
        'host'         => null,
        'port'         => null,
        'log_duration' => null,
        'apiuser_id'   => null,
    ];

    protected $relations = [
        'zone'    => 'IcingaZone',
        'apiuser' => 'IcingaApiUser',
    ];

    public function hasApiUser()
    {
        return $this->getResolvedProperty('apiuser_id') !== null;
    }

    public function getApiUser()
    {
        $id = $this->getResolvedProperty('apiuser_id');
        if ($id === null) {
            throw new RuntimeException('Trying to get API User for Endpoint without such: ' . $this->getObjectName());
        }

        return $this->getRelatedObject('apiuser', $id);
    }

    /**
     * Return a core API, depending on the configuration format
     *
     * @return CoreApi|LegacyDeploymentApi
     */
    public function api()
    {
        $format = $this->connection->settings()->config_format;
        if ($format === 'v2') {
            $api = new CoreApi($this->getRestApiClient());
            $api->setDb($this->getConnection());

            return $api;
        } elseif ($format === 'v1') {
            return new LegacyDeploymentApi($this->connection);
        } else {
            throw new InvalidArgumentException("Unsupported config format: $format");
        }
    }

    /**
     * @return RestApiClient
     */
    public function getRestApiClient()
    {
        $client = new RestApiClient(
            $this->getResolvedProperty('host', $this->getObjectName()),
            $this->getResolvedProperty('port')
        );

        $user = $this->getApiUser();
        $client->setCredentials(
            // TODO: $user->client_dn,
            $user->object_name,
            $user->password
        );

        return $client;
    }

    public function getRenderingZone(IcingaConfig $config = null)
    {
        try {
            if ($zone = $this->getResolvedRelated('zone')) {
                return $zone->getRenderingZone($config);
            }
        } catch (NestingError $e) {
            return self::RESOLVE_ERROR;
        }

        return parent::getRenderingZone($config);
    }

    /**
     * @return int
     */
    public function getResolvedPort()
    {
        $port = $this->getSingleResolvedProperty('port');
        if (null === $port) {
            return 5665;
        } else {
            return (int) $port;
        }
    }

    public function getDescriptiveUrl()
    {
        return sprintf(
            'https://%s@%s:%d/v1/',
            $this->getApiUser()->getObjectName(),
            $this->getResolvedProperty('host', $this->getObjectName()),
            $this->getResolvedPort()
        );
    }

    /**
     * Use duration time renderer helper
     *
     * Avoid complaints for method names with underscore:
     * @codingStandardsIgnoreStart
     *
     * @return string
     */
    protected function renderLog_duration()
    {
        // @codingStandardsIgnoreEnd
        return $this->renderPropertyAsSeconds('log_duration');
    }

    /**
     * Internal property, will not be rendered
     *
     * Avoid complaints for method names with underscore:
     * @codingStandardsIgnoreStart
     *
     * @return string
     */
    protected function renderApiuser_id()
    {
        // @codingStandardsIgnoreEnd
        return '';
    }
}
