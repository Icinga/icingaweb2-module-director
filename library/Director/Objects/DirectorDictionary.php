<?php

namespace Icinga\Module\Director\Objects;

use Icinga\Module\Director\Data\Db\DbObject;

class DirectorDictionary extends DbObject
{
    protected $table = 'director_dictionary';

    protected $keyName = 'id';

    protected $autoincKeyName = 'id';

    protected $defaultProperties = array(
        'id'              => null,
        'dictionary_name' => null,
        'owner'           => null
    );
}
