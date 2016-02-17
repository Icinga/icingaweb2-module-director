<?php

namespace Icinga\Module\Director\Objects;

use Icinga\Module\Director\Core\CoreApi;
use Icinga\Module\Director\Core\RestApiClient;

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

    public function api()
    {
        $client = new RestApiClient(
            $this->getResolvedProperty('host'),
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

    protected function renderLog_duration()
    {
        return $this->renderPropertyAsSeconds('log_duration');
    }

    protected function renderApiuser_id()
    {
        return '';
    }
}
