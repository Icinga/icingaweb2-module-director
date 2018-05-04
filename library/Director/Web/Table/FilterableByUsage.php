<?php

namespace Icinga\Module\Director\Web\Table;

interface FilterableByUsage
{
    public function showOnlyUsed();

    public function showOnlyUnUsed();
}
