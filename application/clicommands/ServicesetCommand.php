<?php

namespace Icinga\Module\Director\Clicommands;

/**
 * Manage Icinga Service Sets
 *
 * Use this command to show, create, modify or delete Icinga Service
 * objects
 */
class ServicesetCommand extends ServiceCommand
{
    protected $type = 'ServiceSet';
}
