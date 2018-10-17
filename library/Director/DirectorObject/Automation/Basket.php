<?php

namespace Icinga\Module\Director\DirectorObject\Automation;

use Icinga\Module\Director\Core\Json;
use Icinga\Module\Director\Data\Db\DbObject;
use Icinga\Module\Director\Db;

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

    protected $keyName = 'basket_name';

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
        $this->chosenObjects = (array) Json::decode($this->get('objects'));
    }

    public function supportsCustomSelectionFor($type)
    {
        if (! array_key_exists($type, $this->chosenObjects)) {
            return false;
        }

        return is_array($this->chosenObjects[$type]);
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
     * This is a weird method, as it is required to deal with raw form data
     *
     * @param $type
     * @param ExportInterface[]|bool $objects
     */
    public function addObjects($type, $objects = true)
    {
        BasketSnapshot::assertValidType($type);

        // '1' -> from Form!
        if ($objects === 'ALL') {
            $objects = true;
        } elseif ($objects === null || $objects === 'IGNORE') {
            return;
        } elseif ($objects === '[]') {
            if (isset($this->chosenObjects[$type])) {
                if (! is_array($this->chosenObjects[$type])) {
                    $this->chosenObjects[$type] = [];
                }
            } else {
                $this->chosenObjects[$type] = [];
            }
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

    public function hasObject($type, $object)
    {
        if (! $this->hasType($type)) {
            return false;
        }

        if ($this->chosenObjects[$type] === true) {
            return true;
        }

        if ($object instanceof ExportInterface) {
            $object = $object->getUniqueIdentifier();
        }

        if (is_array($this->chosenObjects[$type])) {
            return in_array($object, $this->chosenObjects[$type]);
        } else {
            return false;
        }
    }

    /**
     * @param $type
     * @param string $object
     */
    public function addObject($type, $object)
    {
        if (is_array($this->chosenObjects[$type])) {
            $this->chosenObjects[$type][] = $object;
        } else {
            throw new \InvalidArgumentException(sprintf(
                'The Basket "%s" has not been configured for single objects of type "%s"',
                $this->get('basket_name'),
                $type
            ));
        }
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
