<?php

namespace Icinga\Module\Director\DirectorObject\Automation;

use Icinga\Module\Director\Core\Json;
use Icinga\Module\Director\Data\Db\DbObject;

/**
 * Class Basket
 *
 * TODO
 * - create a UUID like in RFC4122
 */
class Basket extends DbObject
{
    const SELECTION_ALL = true;
    const SELECTION_NONE = false;

    protected $validTypes = [
        'host_template',
        'host_object',
        'service_template',
        'service_object',
        'service_apply',
        'import_source',
        'sync_rule'
    ];

    protected $table = 'director_basket';

    protected $keyName = 'uuid';

    protected $chosenObjects = [];

    protected $defaultProperties = [
        'uuid'        => null,
        'basket_name' => null,
        'objects'     => null,
        'owner_type'  => null,
        'owner_value' => null,
    ];

    public function getHexUuid()
    {
        return bin2hex($this->get('uuid'));
    }

    public function listObjectTypes()
    {
        return array_keys($this->objects);
    }

    public function getChosenObjects()
    {
        return $this->chosenObjects;
    }

    public function isEmpty()
    {
        return count($this->getChosenObjects()) === 0;
    }

    protected function onLoadFromDb()
    {
        $this->chosenObjects = Json::decode($this->get('objects'));
    }

    public function setObjects($objects)
    {
        if (empty($objects)) {
            $this->chosenObjects = [];
        } else {
            $this->chosenObjects = [];
            foreach ((array) $objects as $type => $object) {
                $this->addObjects($type, $object);
            }
        }

        return $this;
    }

    /**
     * @param $type
     * @param ExportInterface[]|bool $objects
     */
    public function addObjects($type, $objects = true)
    {
        // '1' -> from Form!
        if ($objects === 'ALL') {
            $objects = true;
        } elseif ($objects === null || $objects === 'IGNORE') {
            return;
        } elseif ($objects === '[]') {
            $objects = [];
        }

        if ($objects === true) {
            $this->chosenObjects[$type] = true;
        } elseif ($objects === '0') {
            // nothing
        } else {
            foreach ($objects as $object) {
                $this->addObject($type, $object);
            }

            if (array_key_exists($type, $this->chosenObjects)) {
                ksort($this->chosenObjects[$type]);
            }
        }

        $this->reallySet('objects', Json::encode($this->chosenObjects));
    }

    /**
     * @param $type
     * @param string $object
     */
    public function addObject($type, $object)
    {
        // TODO: make sure array exists - and is not boolean
        $this->chosenObjects[$type][] = $object;
    }

    public function hasType($type)
    {
        return isset($this->chosenObjects[$type]);
    }

    protected function beforeStore()
    {
        if (! $this->hasBeenLoadedFromDb()) {
            // TODO: This is BS, use a real UUID
            $this->set('uuid', hex2bin(substr(sha1(microtime(true) . rand(1, 100000)), 0, 32)));
        }
    }
}
