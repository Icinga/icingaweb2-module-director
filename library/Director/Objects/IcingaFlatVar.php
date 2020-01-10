<?php

namespace Icinga\Module\Director\Objects;

use Icinga\Module\Director\CustomVariable\CustomVariable;
use Icinga\Module\Director\Data\Db\DbObject;
use Icinga\Module\Director\Db;

class IcingaFlatVar extends DbObject
{
    protected $table = 'icinga_flat_var';

    protected $keyName = [
        'var_checksum',
        'flatname_checksum'
    ];

    protected $defaultProperties = [
        'var_checksum'      => null,
        'flatname_checksum' => null,
        'flatname'          => null,
        'flatvalue'         => null,
    ];

    protected $binaryProperties = [
        'var_checksum',
        'flatname_checksum',
    ];

    public static function generateForCustomVar(CustomVariable $var, Db $db)
    {
        $flatVars = static::forCustomVar($var, $db);
        foreach ($flatVars as $flat) {
            $flat->store();
        }

        return $flatVars;
    }

    public static function forCustomVar(CustomVariable $var, Db $db)
    {
        $flat = [];
        $varSum = $var->checksum();
        $var->flatten($flat, $var->getKey());
        $flatVars = [];

        foreach ($flat as $name => $value) {
            $flatVar = static::create([
                'var_checksum'      => $varSum,
                'flatname_checksum' => sha1($name, true),
                'flatname'          => $name,
                'flatvalue'         => $value,
            ], $db);

            $flatVar->store();
            $flatVars[] = $flatVar;
        }

        return $flatVars;
    }
}
