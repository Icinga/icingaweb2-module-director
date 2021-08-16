<?php

namespace Icinga\Module\Director\Db\Branch;

class MergeErrorRecreateOnMerge extends MergeError
{
    public function prepareMessage()
    {
        return sprintf(
            $this->translate('Cannot recreate %s %s'),
            $this->getObjectTypeName(),
            $this->getNiceObjectName()
        );
    }
}
