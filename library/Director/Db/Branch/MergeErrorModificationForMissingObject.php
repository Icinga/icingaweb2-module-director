<?php

namespace Icinga\Module\Director\Db\Branch;

class MergeErrorModificationForMissingObject extends MergeError
{
    public function prepareMessage()
    {
        return sprintf(
            $this->translate('Cannot apply modification for %s %s, object does not exist'),
            $this->getObjectTypeName(),
            $this->getNiceObjectName()
        );
    }
}
