<?php

namespace Icinga\Module\Director\DirectorObject\Lookup;

use gipfl\IcingaWeb2\Url;
use Icinga\Module\Director\Objects\IcingaHost;
use Ramsey\Uuid\UuidInterface;

interface ServiceInfo
{
    /**
     * The final Service name
     *
     * @return string
     */
    public function getName();

    /**
     * The host the final (rendered, processed) Service belongs to
     *
     * @return string
     */
    public function getHostName();

    /**
     * @return Url
     */
    public function getUrl();

    /**
     * @return UuidInterface
     */
    public function getUuid();

    /**
     * @return bool
     */
    public function requiresOverrides();

    /**
     * @param IcingaHost $host
     * @param $serviceName
     * @return ServiceInfo|false
     */
    public static function find(IcingaHost $host, $serviceName);
}
