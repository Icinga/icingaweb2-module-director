<?php

namespace Icinga\Module\Director\DirectorObject\Automation;

interface ExportInterface
{
    /**
     * @return \stdClass
     */
    public function export();

    // TODO:
    // public function getXyzChecksum();
    public function getUniqueIdentifier();
}
