<?php

namespace Icinga\Module\Director\Restriction;

use Icinga\Authentication\Auth;
use Icinga\Exception\ProgrammingError;
use Icinga\Module\Director\Db;

class ObjectRestriction
{
    /** @var string */
    protected $name;

    /** @var \Zend_Db_Adapter_Abstract */
    protected $db;

    /** @var Auth */
    protected $auth;

    public function __construct(Db $connection, Auth $auth)
    {
        $this->db = $connection->getDbAdapter();
        $this->auth = $auth;
    }

    public function getName()
    {
        if ($this->name === null) {
            throw new ProgrammingError('ObjectRestriction has no name');
        }

        return $this->name;
    }

    public function isRestricted()
    {
        $restrictions = $this->auth->getRestrictions($this->getName());
        return ! empty($restrictions);
    }

    protected function gracefullySplitOnComma($string)
    {
        return preg_split('/\s*,\s*/', $string, -1, PREG_SPLIT_NO_EMPTY);
    }
}
