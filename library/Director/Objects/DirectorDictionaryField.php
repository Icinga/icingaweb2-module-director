<?php

namespace Icinga\Module\Director\Objects;

use Icinga\Module\Director\Data\Db\DbObjectWithSettings;

class DirectorDictionaryField extends DbObjectWithSettings
{
    protected $table = 'director_dictionary_field';

    protected $keyName = 'id';

    protected $autoincKeyName = 'id';

    protected $defaultProperties = array(
        'id'             => null,
        'dictionary_id'  => null,
        'varname'        => null,
        'caption'        => null,
        'description'    => null,
        'datatype'       => null,
        'format'         => null,
        'is_required'    => null,
        'allow_multiple' => null
    );

    protected $settingsTable = 'director_dictionary_field_setting';

    protected $settingsRemoteId = 'dictionary_field_id';
}
