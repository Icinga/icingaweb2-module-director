<?php

namespace Icinga\Module\Director\Clicommands;

use Icinga\Module\Director\Cli\ObjectCommand;

/**
 * Manage Icinga Endpoints
 *
 * Use this command to show, create, modify or delete Icinga Endpoint
 * objects
 */
class EndpointCommand extends ObjectCommand
{
    public function statusAction()
    {
        print_r($this->api()->getStatus());
    }
}
