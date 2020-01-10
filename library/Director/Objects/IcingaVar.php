<?php

namespace Icinga\Module\Director\Objects;

use Icinga\Module\Director\CustomVariable\CustomVariable;
use Icinga\Module\Director\Data\Db\DbObject;
use Icinga\Module\Director\Db;

class IcingaVar extends DbObject
{
    protected $table = 'icinga_var';

    protected $keyName = 'checksum';

    /** @var CustomVariable */
    protected $var;

    protected $defaultProperties = [
        'checksum'          => null,
        'rendered_checksum' => null,
        'varname'           => null,
        'varvalue'          => null,
        'rendered'          => null
    ];

    protected $binaryProperties = [
        'checksum',
        'rendered_checksum',
    ];

    /**
     * @param CustomVariable $customVar
     * @param Db $db
     *
     * @return static
     */
    public static function forCustomVar(CustomVariable $customVar, Db $db)
    {
        $rendered = $customVar->render();

        $var = static::create(array(
            'checksum'          => $customVar->checksum(),
            'rendered_checksum' => sha1($rendered, true),
            'varname'           => $customVar->getKey(),
            'varvalue'          => $customVar->toJson(),
            'rendered'          => $rendered,
        ), $db);

        $var->var = $customVar;

        return $var;
    }

    /**
     * @param CustomVariable $customVar
     * @param Db $db
     *
     * @return static
     * @throws \Icinga\Module\Director\Exception\DuplicateKeyException
     */
    public static function generateForCustomVar(CustomVariable $customVar, Db $db)
    {
        $var = static::forCustomVar($customVar, $db);
        $var->store();
        return $var;
    }

    protected function onInsert()
    {
        IcingaFlatVar::generateForCustomVar($this->var, $this->getConnection());
    }
}
