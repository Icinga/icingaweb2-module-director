<?php

namespace Icinga\Module\Director\Data;

use gipfl\Json\JsonDecodeException;
use gipfl\Json\JsonString;
use Icinga\Module\Director\Data\Db\DbObject;
use Icinga\Module\Director\Db;
use Icinga\Module\Director\DirectorObject\Automation\Basket;
use Icinga\Module\Director\Objects\DirectorJob;
use Icinga\Module\Director\Objects\IcingaHost;
use Icinga\Module\Director\Objects\IcingaService;
use Icinga\Module\Director\Objects\IcingaServiceSet;
use Icinga\Module\Director\Objects\ImportSource;
use Icinga\Module\Director\Objects\SyncRule;
use InvalidArgumentException;
use Ramsey\Uuid\Uuid;
use stdClass;

class ObjectImporter
{
    protected static $templatesOnly = [
        IcingaHost::class,
        IcingaService::class,
        IcingaServiceSet::class,
    ];

    /** @var Db */
    protected $db;

    public function __construct(Db $db)
    {
        $this->db = $db;
    }

    /**
     * @param class-string|DbObject $implementation
     * @param stdClass $plain
     * @return DbObject
     * @throws JsonDecodeException
     */
    public function import(string $implementation, stdClass $plain): DbObject
    {
        $this->assertTemplate($implementation, $plain);
        $this->fixRelations($implementation, $plain);
        $this->applyOtherWorkarounds($implementation, $plain);
        $this->fixLegacyBaskets($implementation, $plain);
        $this->fixSubObjects($implementation, $plain);

        $object = $this->loadExistingObject($implementation, $plain);
        if ($object === null) {
            $object = $implementation::create([], $this->db);
        }

        $properties = (array) $plain;
        unset($properties['fields']);
        unset($properties['originalId']);
        if ($implementation === Basket::class) {
            if (isset($properties['objects']) && is_string($properties['objects'])) {
                $properties['objects'] = JsonString::decode($properties['objects']);
            }
        }
        $object->setProperties($properties);

        return $object;
    }

    protected function fixLegacyBaskets(string $implementation, stdClass $plain)
    {
        // TODO: Check, whether current export sets modifiers = [] in case there is none
        if ($implementation == ImportSource::class) {
            if (!isset($plain->modifiers)) {
                $plain->modifiers = [];
            }
        }
    }

    protected function applyOtherWorkarounds(string $implementation, stdClass $plain)
    {
        if ($implementation === SyncRule::class) {
            if (isset($plain->properties)) {
                $plain->syncProperties = $plain->properties;
                unset($plain->properties);
            }
        }
    }

    protected function fixSubObjects(string $implementation, stdClass $plain)
    {
        if ($implementation === IcingaServiceSet::class) {
            foreach ($plain->services as $service) {
                unset($service->fields);
            }
            // Hint: legacy baskets are carrying service names as object keys, new baskets have arrays
            $plain->services = array_values((array) $plain->services);
        }
    }

    protected function fixRelations(string $implementation, stdClass $plain)
    {
        if ($implementation === DirectorJob::class) {
            $settings = $plain->settings;
            $source = $settings->source ?? null;
            if ($source && !isset($settings->source_id)) {
                $settings->source_id = ImportSource::load($source, $this->db)->get('id');
                unset($settings->source);
            }
            $rule = $settings->rule ?? null;
            if ($rule && !isset($settings->rule_id)) {
                $settings->rule_id = SyncRule::load($rule, $this->db)->get('id');
                unset($settings->rule);
            }
        }
    }

    /**
     * @param class-string<DbObject> $implementation
     * @param stdClass $plain
     * @return DbObject|null
     */
    protected function loadExistingObject(string $implementation, stdClass $plain): ?DbObject
    {
        if (
            isset($plain->uuid)
            && $instance = $implementation::loadWithUniqueId(Uuid::fromString($plain->uuid), $this->db)
        ) {
            return $instance;
        }

        if ($implementation === IcingaService::class) {
            $key = [
                'object_type' => 'template',
                'object_name' => $plain->object_name
            ];
        } else {
            $dummy = $implementation::create();
            $keyColumn = $dummy->getKeyName();
            if (is_array($keyColumn)) {
                if (empty($keyColumn)) {
                    throw new \RuntimeException("$implementation has an empty keyColumn array");
                }
                $key = [];
                foreach ($keyColumn as $column) {
                    if (isset($plain->$column)) {
                        $key[$column] = $plain->$column;
                    }
                }
            } else {
                $key = $plain->$keyColumn;
            }
        }

        return $implementation::loadOptional($key, $this->db);
    }

    protected function assertTemplate(string $implementation, stdClass $plain)
    {
        if (! in_array($implementation, self::$templatesOnly)) {
            return;
        }
        if ($plain->object_type !== 'template') {
            throw new InvalidArgumentException(sprintf(
                'Can import only Templates, got "%s" for "%s"',
                $plain->object_type,
                $plain->name
            ));
        }
    }
}
