<?php

namespace Icinga\Module\Director\DirectorObject;

use Icinga\Module\Director\Data\Db\DbObject;

class IcingaObjectModifications
{

    public $objectType;
    public $objectName;
    public $modifications = [];

    public function __construct($objectType, $objectName)
    {
        $this->objectType = $objectType;
        $this->objectName = $objectName;
    }

    public function addModification(IcingaModifiedAttribute $modifiedAttribute)
    {
        foreach ($modifiedAttribute->getModifiedAttributes() as $key => $value) {
            $this->modifications[$key] = $value;
        }
    }
}
