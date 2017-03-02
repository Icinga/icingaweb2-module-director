<?php

namespace Icinga\Module\Director\Clicommands;

use Icinga\Module\Director\Cli\ObjectsCommand;

/**
 * Manage Icinga Service Sets
 *
 * Use this command to list Icinga Service Set objects
 */
class ServicesetsCommand extends ObjectsCommand
{
    protected $type = 'ServiceSet';
}
