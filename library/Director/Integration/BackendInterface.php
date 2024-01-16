<?php

namespace Icinga\Module\Director\Integration;

use Icinga\Web\Url;

interface BackendInterface
{
    /**
     * Whether the backend has the given host
     *
     * @param ?string $hostName
     *
     * @return bool
     */
    public function hasHost(?string $hostName): bool;

    /**
     * Whether the backend has the given service of the specified host
     *
     * @param ?string $hostName
     * @param ?string $serviceName
     *
     * @return bool
     */
    public function hasService(?string $hostName, ?string $serviceName): bool;

    /**
     * Whether an authenticated user has the permission (is not restricted) to modify given host
     *
     * @param ?string $hostName
     *
     * @return bool
     */
    public function canModifyHost(?string $hostName): bool;

    /**
     * Whether an authenticated user has the permission (is not restricted) to modify given service of specified host
     *
     * @param ?string $hostName
     * @param ?string $serviceName
     *
     * @return bool
     */
    public function canModifyService(?string $hostName, ?string $serviceName): bool;

    /**
     * Get the url of given host
     *
     * @param ?string $hostName
     *
     * @return Url
     */
    public function getHostUrl(?string $hostName): ?Url;
}
