<?php

namespace Icinga\Module\Director\Objects;

use Icinga\Module\Director\Data\Db\DbObjectWithSettings;

class ImportSource extends DbObjectWithSettings
{
    protected $table = 'import_source';

    protected $keyName = 'id';

    protected $autoincKeyName = 'id';

    protected $defaultProperties = array(
        'id'             => null,
        'source_name'    => null,
        'provider_class' => null,
        'key_column'     => null
    );

    protected $settingsTable = 'import_source_setting';

    protected $settingsRemoteId = 'source_id';
}
