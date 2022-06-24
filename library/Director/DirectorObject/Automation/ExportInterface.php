<?php

namespace Icinga\Module\Director\DirectorObject\Automation;

use Icinga\Module\Director\Db;

interface ExportInterface
{
    /**
     * @deprecated
     * @return \stdClass
     */
    public function export();

    public static function import($plain, Db $db, $replace = false);

    // TODO:
    // public function getXyzChecksum();
    public function getUniqueIdentifier();
}
