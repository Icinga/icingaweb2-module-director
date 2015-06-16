<?php

namespace Icinga\Module\Director\IcingaConfig;

interface IcingaConfigRenderer
{
    public function toConfigString();
    public function __toString();
}
