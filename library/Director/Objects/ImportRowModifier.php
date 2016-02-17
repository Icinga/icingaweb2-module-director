<?php

namespace Icinga\Module\Director\Objects;

use Icinga\Module\Director\Data\Db\DbObjectWithSettings;

class ImportRowModifier extends DbObjectWithSettings
{
    protected $table = 'import_row_modifier';

    protected $keyName = 'id';

    protected $autoincKeyName = 'id';

    protected $defaultProperties = array(
        'id'             => null,
        'source_id'      => null,
        'property_name'  => null,
        'provider_class' => null,
        'priority'       => null,
    );

    protected $settingsTable = 'import_row_modifier_setting';

    protected $settingsRemoteId = 'row_modifier_id';
}
