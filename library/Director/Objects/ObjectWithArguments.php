<?php

namespace Icinga\Module\Director\Objects;

interface ObjectWithArguments
{
    /**
     * @return boolean
     */
    public function gotArguments();

    /**
     * @return IcingaArguments
     */
    public function arguments();

    public function unsetArguments();
}
