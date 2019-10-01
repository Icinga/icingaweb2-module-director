<?php

namespace Icinga\Module\Director\Objects;

use Icinga\Module\Director\Data\Db\DbObject;

class DirectorDatafieldCategory extends DbObject
{
    protected $table = 'director_datafield_category';

    protected $keyName = 'id';

    protected $autoincKeyName = 'id';

    protected $defaultProperties = [
        'id'            => null,
        'category_name' => null,
        'description'   => null,
    ];
}
