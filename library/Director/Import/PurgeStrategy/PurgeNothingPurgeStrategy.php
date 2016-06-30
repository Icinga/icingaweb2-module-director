<?php

namespace Icinga\Module\Director\Import\PurgeStrategy;

class PurgeNothingPurgeStrategy extends PurgeStrategy
{
    public function listObjectsToPurge()
    {
        return array();
    }
}
