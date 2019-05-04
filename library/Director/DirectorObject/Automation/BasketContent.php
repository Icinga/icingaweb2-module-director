<?php

namespace Icinga\Module\Director\DirectorObject\Automation;

use Icinga\Module\Director\Data\Db\DbObject;

class BasketContent extends DbObject
{
    protected $objects;

    protected $table = 'director_basket_content';

    protected $keyName = 'checksum';

    protected $defaultProperties = [
        'checksum' => null,
        'summary'  => null,
        'content'  => null,
    ];

    protected $binaryProperties = [
        'checksum'
    ];
}
