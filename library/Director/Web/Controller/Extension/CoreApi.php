<?php

namespace Icinga\Module\Director\Web\Controller\Extension;

use Icinga\Module\Director\Objects\IcingaEndpoint;
use Icinga\Module\Director\Core\CoreApi as Api;

trait CoreApi
{
    /** @var Api */
    private $api;

    /**
     * @return Api|null
     */
    public function getApiIfAvailable()
    {
        if ($this->api === null) {
            if ($this->db()->hasDeploymentEndpoint()) {
                $endpoint = $this->db()->getDeploymentEndpoint();
                $this->api = $endpoint->api();
            }
        }

        return $this->api;
    }

    /**
     * @param string $endpointName
     * @return Api
     */
    public function api($endpointName = null)
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
}
