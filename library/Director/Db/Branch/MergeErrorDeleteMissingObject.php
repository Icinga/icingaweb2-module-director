<?php

namespace Icinga\Module\Director\Db\Branch;

class MergeErrorDeleteMissingObject extends MergeError
{
    public function prepareMessage()
    {
        return sprintf(
            $this->translate('Cannot delete %s %s, it does not exist'),
            $this->getObjectTypeName(),
            $this->getNiceObjectName()
        );
    }
}
