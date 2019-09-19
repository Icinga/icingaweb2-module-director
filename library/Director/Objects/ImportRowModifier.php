<?php

namespace Icinga\Module\Director\Objects;

use Icinga\Module\Director\Data\Db\DbObjectWithSettings;
use Icinga\Module\Director\Hook\PropertyModifierHook;
use Icinga\Module\Director\Objects\Extension\PriorityColumn;
use RuntimeException;

class ImportRowModifier extends DbObjectWithSettings
{
    use PriorityColumn;

    protected $table = 'import_row_modifier';

    protected $keyName = 'id';

    protected $autoincKeyName = 'id';

    protected $defaultProperties = [
        'id'              => null,
        'source_id'       => null,
        'property_name'   => null,
        'provider_class'  => null,
        'target_property' => null,
        'priority'        => null,
        'description'     => null,
    ];

    protected $settingsTable = 'import_row_modifier_setting';

    protected $settingsRemoteId = 'row_modifier_id';

    private $hookInstance;

    public function getInstance()
    {
        if ($this->hookInstance === null) {
            $class = $this->get('provider_class');
            /** @var PropertyModifierHook $obj */
            if (! class_exists($class)) {
                throw new RuntimeException(sprintf(
                    'Cannot instantiate Property modifier %s',
                    $class
                ));
            }
            $obj = new $class;
            $obj->setSettings($this->getSettings());
            $obj->setTargetProperty($this->get('target_property'));
            $obj->setDb($this->connection);
            $this->hookInstance = $obj;
        }

        return $this->hookInstance;
    }

    /**
     * @return \stdClass
     */
    public function export()
    {
        $properties =  $this->getProperties();
        unset($properties['id']);
        unset($properties['source_id']);
        $properties['settings'] = $this->getInstance()->exportSettings();
        ksort($properties);

        return (object) $properties;
    }

    protected function beforeStore()
    {
        if (! $this->hasBeenLoadedFromDb() && $this->get('priority') === null) {
            $this->setNextPriority('source_id');
        }
    }

    protected function onInsert()
    {
        $this->refreshPriortyProperty();
    }
}
