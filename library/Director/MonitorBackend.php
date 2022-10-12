<?php

namespace Icinga\Module\Director;

interface MonitorBackend
{
    public function isAvailable();

    public function hasHost($hostname);

    public function hasService($hostname, $service);

    public function getHostLink($title, $hostname, array $attributes = null);

    public function getHostState($hostname);
}
