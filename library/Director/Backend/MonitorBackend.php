<?php

namespace Icinga\Module\Director\Backend;

use Icinga\Web\Url;

interface MonitorBackend
{
    public function isAvailable(): bool;

    public function hasHost(string $hostname): bool;

    public function hasService(string $hostname, string $service): bool;

    public function canModifyHost(string $hostName): bool;

    public function canModifyService(string $hostName, string $serviceName): bool;

    public function getHostUrl(string $hostname): Url;
}
