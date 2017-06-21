<?php

namespace Icinga\Module\Director\Restriction;

use Icinga\Authentication\Auth;
use Icinga\Exception\ProgrammingError;
use Icinga\Module\Director\Db;
use Icinga\Module\Director\Objects\IcingaObject;
use Zend_Db_Select as ZfSelect;

abstract class ObjectRestriction
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

    abstract public function allows(IcingaObject $object);

    /**
     * Apply the restriction to the given Hosts Query
     *
     * We assume that the query wants to fetch hosts and that therefore the
     * icinga_host table already exists in the given query, using the $tableAlias
     * alias.
     *
     * @param ZfSelect $query
     * @param string $tableAlias
     */
    abstract protected function filterQuery(ZfSelect $query, $tableAlias = 'o');

    public function applyToQuery(ZfSelect $query, $tableAlias = 'o')
    {
        if ($this->isRestricted()) {
            $this->filterQuery($query, $tableAlias);
        }

        return $query;
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

    protected function getQueryTableByAlias(ZfSelect $query, $tableAlias)
    {
        $from = $query->getPart(ZfSelect::FROM);
        if (! array_key_exists($tableAlias, $from)) {
            throw new ProgrammingError(
                'Cannot restrict query with alias "%s", got %s',
                $tableAlias,
                json_encode($from)
            );
        }

        return $from[$tableAlias]['tableName'];
    }

    protected function gracefullySplitOnComma($string)
    {
        return preg_split('/\s*,\s*/', $string, -1, PREG_SPLIT_NO_EMPTY);
    }
}
