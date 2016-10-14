<?php

namespace Icinga\Module\Director\IcingaConfig;

interface IcingaConfigRenderer
{
    public function toConfigString();
    public function toLegacyConfigString();
    public function __toString();
}
