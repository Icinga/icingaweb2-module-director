<?php

namespace Icinga\Module\Director\Objects;

use Icinga\Exception\ProgrammingError;
use Icinga\Module\Director\Core\CoreApi;
use Icinga\Module\Director\Core\LegacyDeploymentApi;
use Icinga\Module\Director\Core\RestApiClient;
use Icinga\Module\Director\IcingaConfig\IcingaConfig;

class IcingaEndpoint extends IcingaObject
{
    protected $table = 'icinga_endpoint';

    protected $supportsImports = true;

    protected $defaultProperties = array(
        'id'                    => null,
        'zone_id'               => null,
        'object_name'           => null,
        'object_type'           => null,
        'disabled'              => 'n',
        'host'                  => null,
        'port'                  => null,
        'log_duration'          => null,
        'apiuser_id'            => null,
    );

    protected $relations = array(
        'zone'    => 'IcingaZone',
        'apiuser' => 'IcingaApiUser',
    );

    public function hasApiUser()
    {
        return $this->getResolvedProperty('apiuser_id') !== null;
    }

    public function getApiUser()
    {
        return $this->getRelatedObject(
            'apiuser',
            $this->getResolvedProperty('apiuser_id')
        );
    }

    /**
     * Return a core API, depending on the configuration format
     *
     * @return CoreApi|LegacyDeploymentApi
     *
     * @throws ProgrammingError  When configured config_format is unknown
     */
    public function api()
    {
        $format = $this->connection->settings()->config_format;
        if ($format === 'v2') {
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

            return new CoreApi($client);
        }
        elseif ($format === 'v1') {
            return new LegacyDeploymentApi($this->connection);
        }
        else {
            throw new ProgrammingError('Unsupported config format: %s', $format);
        }
    }

    public function getRenderingZone(IcingaConfig $config = null)
    {
	if($this->zone_id) {
	    $thisZone = $this->getRelatedObject(
	        'zone',
	        $this->zone_id
	    );
	    if($thisZone && $parentZone = $thisZone->get('parent_id')) {
	        return $config->getZoneName($parentZone);
	    }
	    return $config->getZoneName($this->zone_id);
	}

	if ($this->isTemplate() || $this->isApplyRule()) {
	    return $this->connection->getDefaultGlobalZoneName();
	}

	return $this->connection->getMasterZoneName();
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
