<?php

namespace Icinga\Module\Director\Clicommands;

use Icinga\Module\Director\Cli\ObjectCommand;
use Icinga\Module\Director\Objects\IcingaHost;

/**
 * Manage Icinga Services
 *
 * Use this command to show, create, modify or delete Icinga Service
 * objects
 */
class ServiceCommand extends ObjectCommand
{

    protected function load($name)
    {
        return parent::load($this->makeServiceKey($this->getName()));
    }

    protected function exists($name)
    {
        return parent::exists($this->makeServiceKey($this->getName()));
    }

    protected function makeServiceKey($name)
    {
        if ($host = $this->params->get('host')) {
            return array(
                'object_name' => $name,
                'host_id'     => IcingaHost::load($host, $this->db())->id,
            );
        } else {
            return array(
                'object_name' => $name,
                'object_type' => 'template',
            );
        }
    }
}
