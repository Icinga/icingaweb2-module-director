<?php

namespace Icinga\Module\Director\Cli;

use Icinga\Cli\Params;
use Icinga\Exception\MissingParameterException;
use Icinga\Module\Director\Objects\IcingaObject;
use InvalidArgumentException;

class ObjectCommand extends Command
{
    protected $type;

    private $name;

    private $object;

    private $experimentalFlags = array();

    public function init()
    {
        $this->shiftExperimentalFlags();
    }

    /**
     * Show a specific object
     *
     * Use this command to show single objects rendered as Icinga 2
     * config or in JSON format.
     *
     * USAGE
     *
     * icingacli director <type> show <name> [options]
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
    public function showAction()
    {
        $db = $this->db();
        $object = $this->getObject();
        if ($this->params->shift('resolved')) {
            $object = $object::fromPlainObject($object->toPlainObject(true), $db);
        }

        if ($this->params->shift('json')) {
            $noDefaults = $this->params->shift('no-defaults', false);
            $data = $object->toPlainObject(false, $noDefaults);
            echo $this->renderJson($data, !$this->params->shift('no-pretty'));
        } else {
            echo $object;
        }
    }

    /**
     * Create a new object
     *
     * Use this command to create a new Icinga object
     *
     * USAGE
     *
     * icingacli director <type> create [<name>] [options]
     *
     * OPTIONS
     *
     *   --<key> <value>   Provide all properties as single command line
     *                     options
     *   --json            Otherwise provide all options as a JSON string
     *
     * EXAMPLES
     *
     *   icingacli director host create localhost \
     *     --imports generic-host \
     *     --address 127.0.0.1 \
     *     --vars.location 'My datacenter'
     *
     *   icingacli director host create localhost \
     *     --json '{ "address": "127.0.0.1" }'
     */
    public function createAction()
    {
        $type = $this->getType();
        $name = $this->params->shift();

        $props = $this->remainingParams();
        if (! array_key_exists('object_type', $props)) {
            $props['object_type'] = 'object';
        }

        if ($name) {
            if (array_key_exists('object_name', $props)) {
                if ($name !== $props['object_name']) {
                    $this->fail(sprintf(
                        "Name '%s' conflicts with object_name '%s'\n",
                        $name,
                        $props['object_name']
                    ));
                }
            } else {
                $props['object_name'] = $name;
            }
        }

        if (! array_key_exists('object_name', $props)) {
            $this->fail('Cannot create an object with at least an object name');
        }

        $object = IcingaObject::createByType(
            $type,
            $props,
            $this->db()
        );

        if ($object->store()) {
            printf("%s '%s' has been created\n", $type, $name);
            if ($this->hasExperimental('live-creation')) {
                if ($this->api()->createObjectAtRuntime($object)) {
                    echo "Live creation for '$name' succeeded\n";
                } else {
                    echo "Live creation for '$name' succeeded\n";
                    exit(1);
                }

                if ($type === 'Host' && $this->hasExperimental('immediate-check')) {
                    echo "Waiting for check result...";
                    flush();
                    if ($res = $this->api()->checkHostAndWaitForResult($name)) {
                        echo " done\n" . $res->output . "\n";
                    } else {
                        echo "TIMEOUT\n";
                    }
                }
            }

            exit(0);
        } else {
            printf("%s '%s' has not been created\n", $type, $name);
            exit(1);
        }
    }

    /**
     * Modify an existing objects properties
     *
     * Use this command to modify specific properties of an existing
     * Icinga object
     *
     * USAGE
     *
     * icingacli director <type> set <name> [options]
     *
     * OPTIONS
     *
     *   --<key> <value>   Provide all properties as single command line
     *                     options
     *   --append-<key> <value> Appends to array values, like `imports`,
     *                     `groups` or `vars.system_owners`
     *   --remove-<key> [<value>] Remove a specific property, eventually only
     *                     when matching `value`. In case the property is an
     *                     array it will remove just `value` when given
     *   --json            Otherwise provide all options as a JSON string
     *   --replace         Replace all object properties with the given ones
     *   --auto-create     Create the object in case it does not exist
     *
     * EXAMPLES
     *
     *   icingacli director host set localhost \
     *     --address 127.0.0.2 \
     *     --vars.location 'Somewhere else'
     *
     *   icingacli director host set localhost \
     *     --json '{ "address": "127.0.0.2" }'
     */
    public function setAction()
    {
        $name = $this->getName();

        if ($this->params->shift('auto-create') && ! $this->exists($name)) {
            $action = 'created';
            $object = $this->create($name);
        } else {
            $action = 'modified';
            $object = $this->getObject();
        }

        $appends = $this->stripPrefixedProperties($this->params, 'append-');
        $remove = $this->stripPrefixedProperties($this->params, 'remove-');

        if ($this->params->shift('replace')) {
            $new = $this->create($name)->setProperties($this->remainingParams());
            $object->replaceWith($new);
        } else {
            $object->setProperties($this->remainingParams());
        }

        $this->appendToArrayProperties($object, $appends);
        $this->removeProperties($object, $remove);
        if ($object->hasBeenModified() && $object->store()) {
            printf("%s '%s' has been %s\n", $this->getType(), $this->name, $action);
            exit(0);
        }

        printf("%s '%s' has not been modified\n", $this->getType(), $name);
        exit(0);
    }

    /**
     * Delete a specific object
     *
     * Use this command to delete a single Icinga object
     *
     * USAGE
     *
     * icingacli director <type> delete <name>
     *
     * EXAMPLES
     *
     * icingacli director host delete localhost2
     *
     * icingacli director host delete localhost{3..8}
     */
    public function deleteAction()
    {
        $type = $this->getType();

        foreach ($this->shiftOneOrMoreNames() as $name) {
            if ($this->load($name)->delete()) {
                printf("%s '%s' has been deleted\n", $type, $name);
            } else {
                printf("Something went wrong while deleting %s '%s'\n", $type, $name);
                exit(1);
            }

            $this->object = null;
        }
        exit(0);
    }

    /**
     * Whether a specific object exists
     *
     * Use this command to find out whether a single Icinga object exists
     *
     * USAGE
     *
     * icingacli director <type> exists <name>
     */
    public function existsAction()
    {
        $name = $this->getName();
        $type = $this->getType();
        if ($this->exists($name)) {
            printf("%s '%s' exists\n", $type, $name);
            exit(0);
        } else {
            printf("%s '%s' does not exist\n", $type, $name);
            exit(1);
        }
    }

    /**
     * Clone an existing object
     *
     * Use this command to clone a specific object
     *
     * USAGE
     *
     * icingacli director <type> clone <name> --from <original> [options]
     *
     * OPTIONS
     *   --from <original> The name of the object you want to clone
     *   --<key> <value>   Override specific properties while cloning
     *   --replace         In case an object <name> already exists replace
     *                     it with the clone
     *   --flat            Do no keep inherited properties but create a flat
     *                     object with all resolved/inherited properties
     *
     * EXAMPLES
     *
     *   icingacli director host clone localhost2 --from localhost
     *
     *   icingacli director host clone localhost{3..8} --from localhost2
     *
     *   icingacli director host clone localhost3 --from localhost \
     *     --address 127.0.0.3
     */
    public function cloneAction()
    {
        $fromName = $this->params->shiftRequired('from');
        $from = $this->load($fromName);

        // $name = $this->getName();
        $type = $this->getType();

        $resolve = $this->params->shift('flat');
        $replace = $this->params->shift('replace');

        $from->setProperties($this->remainingParams());

        foreach ($this->shiftOneOrMoreNames() as $name) {
            $object = $from::fromPlainObject(
                $from->toPlainObject($resolve),
                $from->getConnection()
            );

            $object->set('object_name', $name);

            if ($replace && $this->exists($name)) {
                $object = $this->load($name)->replaceWith($object);
            }

            if ($object->hasBeenModified() && $object->store()) {
                printf("%s '%s' has been cloned from %s\n", $type, $name, $fromName);
            } else {
                printf("%s '%s' has not been modified\n", $this->getType(), $name);
            }
        }

        exit(0);
    }

    protected function appendToArrayProperties(IcingaObject $object, $properties)
    {
        foreach ($properties as $key => $value) {
            $current = $object->$key;
            if ($current === null) {
                $current = [$value];
            } elseif (is_array($current)) {
                $current[] = $value;
            } else {
                throw new InvalidArgumentException(sprintf(
                    'I can only append to arrays, %s is %s',
                    $key,
                    var_export($current, 1)
                ));
            }

            $object->$key = $current;
        }
    }

    protected function removeProperties(IcingaObject $object, $properties)
    {
        foreach ($properties as $key => $value) {
            if ($value === true) {
                $object->$key = null;
            }
            $current = $object->$key;
            if ($current === null) {
                continue;
            } elseif (is_array($current)) {
                $new = [];
                foreach ($current as $item) {
                    if ($item !== $value) {
                        $new[] = $item;
                    }
                }
                $object->$key = $new;
            } elseif (is_string($current)) {
                if ($current === $value) {
                    $object->$key = null;
                }
            } else {
                throw new InvalidArgumentException(sprintf(
                    'I can only remove strings or from arrays, %s is %s',
                    $key,
                    var_export($current, 1)
                ));
            }
        }
    }

    protected function stripPrefixedProperties(Params $params, $prefix = 'append-')
    {
        $appends = [];
        $len = strlen($prefix);

        foreach ($params->getParams() as $key => $value) {
            if (substr($key, 0, $len) === $prefix) {
                $appends[substr($key, $len)] = $value;
            }
        }

        foreach ($appends as $key => $value) {
            $params->shift("$prefix$key");
        }

        return $appends;
    }

    protected function shiftOneOrMoreNames()
    {
        $names = array();
        while ($name = $this->params->shift()) {
            $names[] = $name;
        }

        if (empty($names)) {
            throw new MissingParameterException('Required object name is missing');
        }

        return $names;
    }

    protected function remainingParams()
    {
        if ($json = $this->params->shift('json')) {
            return (array) $this->parseJson($json);
        } else {
            return $this->params->getParams();
        }
    }

    protected function exists($name)
    {
        return IcingaObject::existsByType(
            $this->getType(),
            $name,
            $this->db()
        );
    }

    protected function load($name)
    {
        return IcingaObject::loadByType(
            $this->getType(),
            $name,
            $this->db()
        );
    }

    protected function create($name)
    {
        return IcingaObject::createByType(
            $this->getType(),
            array(
                'object_type' => 'object',
                'object_name' => $name
            ),
            $this->db()
        );
    }

    /**
     * @return IcingaObject
     */
    protected function getObject()
    {
        if ($this->object === null) {
            $this->object = $this->load($this->getName());
        }

        return $this->object;
    }

    protected function getType()
    {
        if ($this->type === null) {
            // Extract the command class name...
            $className = substr(strrchr(get_class($this), '\\'), 1);
            // ...and strip the Command extension
            $this->type = substr($className, 0, -7);
        }

        return $this->type;
    }

    protected function getName()
    {
        if ($this->name === null) {
            $name = $this->params->shift();
            if (! $name) {
                throw new InvalidArgumentException('Object name parameter is required');
            }

            $this->name = $name;
        }

        return $this->name;
    }

    protected function hasExperimental($flag)
    {
        return array_key_exists($flag, $this->experimentalFlags);
    }

    protected function shiftExperimentalFlags()
    {
        if ($flags = $this->params->shift('experimental')) {
            foreach (preg_split('/,/', $flags) as $flag) {
                $this->experimentalFlags[$flag] = true;
            }
        }

        return $this;
    }
}
