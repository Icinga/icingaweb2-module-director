<?php

namespace Icinga\Module\Director\Cli;

use Icinga\Module\Director\Cli\Command;
use Icinga\Module\Director\Objects\IcingaObject;

class ObjectsCommand extends Command
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
        $result = array();
        foreach ($this->getObjects() as $o) {
            $result[] = $o->getObjectName();
        }

        sort($result);

        if ($this->params->shift('json')) {
            echo $this->renderJson($result, !$this->params->shift('no-pretty'));
        } else {
            foreach ($result as $name) {
                echo $name . "\n";
            }
        }
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
     *   --no-defaults Per default JSON output ships null or default values
     *                 With this flag you will skip those properties
     */
    public function fetchAction()
    {
        $resolved = $this->params->shift('resolved');

        if ($this->params->shift('json')) {
            $noDefaults = $this->params->shift('no-defaults', false);
        } else {
            $this->fail('Currently only json is supported when fetching objects');
        }

        $db = $this->db();
        $res = array();
        foreach ($this->getObjects() as $object) {
            if ($resolved) {
                $object = $object::fromPlainObject($object->toPlainObject(true), $db);
            }

            $res[$object->getObjectName()] = $object->toPlainObject(false, $noDefaults);
        }

        echo $this->renderJson($res, !$this->params->shift('no-pretty'));
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
