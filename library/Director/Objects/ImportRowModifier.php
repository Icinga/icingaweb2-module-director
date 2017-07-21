<?php

namespace Icinga\Module\Director\Objects;

use Icinga\Module\Director\Data\Db\DbObjectWithSettings;

class ImportRowModifier extends DbObjectWithSettings
{
    protected $table = 'import_row_modifier';

    protected $keyName = 'id';

    protected $autoincKeyName = 'id';

    protected $defaultProperties = array(
        'id'              => null,
        'source_id'       => null,
        'property_name'   => null,
        'provider_class'  => null,
        'target_property' => null,
        'priority'        => null,
        'description'     => null,
    );

    protected $settingsTable = 'import_row_modifier_setting';

    protected $settingsRemoteId = 'row_modifier_id';

    private $hookInstance;

    public function getInstance()
    {
        if ($this->hookInstance === null) {
            $obj = new $this->provider_class;
            $obj->setSettings($this->getSettings());
            $obj->setTargetProperty($this->target_property);
            $obj->setDb($this->connection);
            $this->hookInstance = $obj;
        }

        return $this->hookInstance;
    }
}
