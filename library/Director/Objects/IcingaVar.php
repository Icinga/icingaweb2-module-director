<?php

namespace Icinga\Module\Director\Objects;

use Icinga\Module\Director\CustomVariable\CustomVariable;
use Icinga\Module\Director\Data\Db\DbObject;
use Icinga\Module\Director\IcingaConfig\IcingaConfigHelper as c;
use Icinga\Module\Director\Db;

class IcingaVar extends DbObject
{
    protected $table = 'icinga_var';

    protected $keyName = 'checksum';

    /** @var CustomVariable */
    protected $var;

    protected $defaultProperties = array(
        'checksum'          => null,
        'rendered_checksum' => null,
        'varname'           => null,
        'varvalue'          => null,
        'rendered'          => null
    );

    /**
     * @param CustomVariable $var
     * @param Db $db
     *
     * @return static
     */
    public static function forCustomVar(CustomVariable $customVar, Db $db)
    {
        $rendered = static::renderVar($customVar);

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

    protected static function renderVar(CustomVariable $var)
    {
        $renderExpressions = false; // TODO!
        return c::renderKeyValue(
            static::renderKeyName($var->getKey()),
            $var->toConfigStringPrefetchable($renderExpressions)
        );
    }

    protected static function renderKeyName($key)
    {
        if (preg_match('/^[a-z0-9_]+\d*$/i', $key)) {
            return 'vars.' . c::escapeIfReserved($key);
        } else {
            return 'vars[' . c::renderString($key) . ']';
        }
    }

    /**
     * @param CustomVariable $var
     * @param Db $db
     *
     * @return static
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
