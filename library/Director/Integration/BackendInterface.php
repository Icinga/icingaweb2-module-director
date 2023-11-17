<?php

namespace Icinga\Module\Director\Integration;

use Icinga\Web\Url;

interface BackendInterface
{
    /**
     * Whether the backend is available
     *
     * @return bool
     */
    public function isAvailable(): bool;

    /**
     * Whether the backend has the given host
     *
     * @param string $hostname
     *
     * @return bool
     */
    public function hasHost(string $hostname): bool;

    /**
     * Whether the backend has the given service of the specified host
     *
     * @param string $hostname
     * @param string $service
     *
     * @return bool
     */
    public function hasService(string $hostname, string $service): bool;

    /**
     * Whether an authenticated user has the permission (is not restricted) to modify given host
     *
     * @param string $hostName
     *
     * @return bool
     */
    public function canModifyHost(string $hostName): bool;

    /**
     * Whether an authenticated user has the permission (is not restricted) to modify given service of specified host
     *
     * @param string $hostName
     * @param string $serviceName
     *
     * @return bool
     */
    public function canModifyService(string $hostName, string $serviceName): bool;

    /**
     * Get the url of given host
     *
     * @param string $hostname
     *
     * @return Url
     */
    public function getHostUrl(string $hostname): Url;
}
