<?php

namespace Icinga\Module\Director\Import\PurgeStrategy;

use Icinga\Module\Director\Objects\SyncRule;

abstract class PurgeStrategy
{
    private $rule;

    public function __construct(SyncRule $rule)
    {
        $this->rule = $rule;
    }

    protected function getSyncRule()
    {
        return $this->rule;
    }

    abstract public function listObjectsToPurge();

    /**
     * @return PurgeStrategy
     */
    public static function load($name, SyncRule $rule)
    {
        $class = __NAMESPACE__ . '\\' . $name . 'PurgeStrategy';
        return new $class($rule);
    }
}
