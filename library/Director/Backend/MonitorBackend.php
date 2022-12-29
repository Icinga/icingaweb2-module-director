<?php

namespace Icinga\Module\Director\Backend;

use Icinga\Data\Filter\Filter;

interface MonitorBackend
{
    public function isAvailable();

    public function hasHost($hostname);

    public function hasHostWithExtraFilter($hostname, Filter $filter);

    public function hasService($hostname, $service);

    public function hasServiceWithExtraFilter($hostname, $service, Filter $filter);

    public function getHostLink($title, $hostname, array $attributes = null);

    public function getHostState($hostname);
}
