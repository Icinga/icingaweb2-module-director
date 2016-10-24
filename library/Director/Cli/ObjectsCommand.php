<?php

namespace Icinga\Module\Director\Cli;

use Icinga\Module\Director\Cli\Command;
use Icinga\Module\Director\Objects\IcingaObject;

class ObjectCommand extends Command
{
    protected $type;

    private $objects;

    /**
     * List all objects of this type
     *
     * Use this command to get a list of all matching objeccts
     *
     * USAGE
     *
     * icingacli director <types> list [options]
     *
     * OPTIONS
     *
     *   --json        Use JSON format
     *   --no-pretty   JSON is pretty-printed per default (for PHP >= 5.4)
     *                 Use this flag to enforce unformatted JSON
     */
    public function listAction()
    {
        $db = $this->db();
        exit;
    }

    /**
     * Delete a specific object
     *
     * Use this command to delete a single Icinga object
     *
     * USAGE
     *
     * icingacli director <types> fetch [options]
     *
     * OPTIONS
     *
     *   --resolved    Resolve all inherited properties and show a flat
     *                 object
     *   --json        Use JSON format
     *   --no-pretty   JSON is pretty-printed per default (for PHP >= 5.4)
     *                 Use this flag to enforce unformatted JSON
     *   --no-defaults Per default JSON output skips null or default values
     *                 With this flag you will get all properties
     */
    public function fetchAction()
    {
        if ($this->params->shift('json')) {
            $res = array();
            foreach ($this->getObjects() as $object) {
            }
        } else {
            foreach ($this->getObjects() as $object) {
            }
        }
    }

    protected function getObjects()
    {
        if ($this->objects === null) {
            $this->objects = IcingaObject::loadAllByType(
                $this->getType(),
                $this->db()
            );
        }

        return $this->objects;
    }

    protected function getType()
    {
        if ($this->type === null) {
            // Extract the command class name...
            $className = substr(strrchr(get_class($this), '\\'), 1);
            // ...and strip the Command extension
            $this->type = rtrim(substr($className, 0, -7), 's');
        }

        return $this->type;
    }
}
