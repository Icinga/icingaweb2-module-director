<?php

namespace Icinga\Module\Director\Data\Db;

use Icinga\Data\Db\DbQuery;
use Icinga\Data\Filter\Filter;
use Icinga\Exception\NotFoundError;
use Icinga\Exception\NotImplementedError;
use Icinga\Module\Director\Db;
use Zend_Db_Expr as ZfDbExpr;
use Zend_Db_Select as ZfDbSelect;

class IcingaObjectQuery
{
    public const BASE_ALIAS = 'o';

    /** @var Db */
    protected $connection;

    /** @var string */
    protected $type;

    /** @var \Zend_Db_Adapter_Abstract */
    protected $db;

    /** @var ZfDbSelect */
    protected $query;

    /** @var bool */
    protected $resolved;

    /** @var array joined tables, alias => table */
    protected $requiredTables;

    /** @var array maps table aliases, alias => table*/
    protected $aliases;

    /** @var DbQuery */
    protected $dummyQuery;

    /** @var array varname => alias */
    protected $joinedVars = array();

    protected $customVarTable;

    protected $baseQuery;

    /**
     * IcingaObjectQuery constructor.
     *
     * @param string $type
     * @param Db $connection
     * @param bool $resolved
     */
    public function __construct($type, Db $connection, $resolved = true)
    {
        $this->type = $type;
        $this->connection = $connection;
        $this->db = $connection->getDbAdapter();
        $this->resolved = $resolved;
        $baseTable = 'icinga_' . $type;
        $this->baseQuery = $this->db->select()
            ->from(
                array(self::BASE_ALIAS => $baseTable),
                array('name' => 'object_name')
            )->order(self::BASE_ALIAS . '.object_name');
    }

    public function joinVar($name)
    {
        if (! $this->hasJoinedVar($name)) {
            $type = $this->type;
            $alias = $this->safeVarAlias($name);
            $varAlias = "v_$alias";
            // TODO: optionally $varRelation = sprintf('icinga_%s_resolved_var', $type);
            $varRelation = sprintf('icinga_%s_var', $type);
            $idCol = sprintf('%s.%s_id', $alias, $type);

            $joinOn = sprintf('%s = %s.id', $idCol, self::BASE_ALIAS);
            $joinVarOn = $this->db->quoteInto(
                sprintf('%s.checksum = %s.checksum AND %s.varname = ?', $alias, $varAlias, $alias),
                $name
            );

            $this->baseQuery->join(
                array($alias => $varRelation),
                $joinOn,
                array()
            )->join(
                array($varAlias => 'icinga_var'),
                $joinVarOn,
                array($alias => $varAlias . '.varvalue')
            );

            $this->joinedVars[$name] = $varAlias . '.varvalue';
        }

        return $this;
    }

    // Debug only
    public function getSql()
    {
        return (string) $this->baseQuery;
    }

    public function listNames()
    {
        return $this->db->fetchCol(
            $this->baseQuery
        );
    }

    protected function hasJoinedVar($name)
    {
        return array_key_exists($name, $this->joinedVars);
    }

    public function getJoinedVarAlias($name)
    {
        return $this->joinedVars[$name];
    }

    // TODO: recheck this
    protected function safeVarAlias($name)
    {
        $alias = preg_replace('/[^a-zA-Z0-9_]/', '', (string) $name);
        $cnt = 1;
        $checkAlias = $alias;
        while (in_array($checkAlias, $this->joinedVars)) {
            $cnt++;
            $checkAlias = $alias . '_' . $cnt;
        }

        return $checkAlias;
    }

    public function escapedWhere($where)
    {
        $this->baseQuery->where(new ZfDbExpr($where));
    }

    /**
     * @param $column
     * @return string
     * @throws NotFoundError
     * @throws NotImplementedError
     */
    public function getAliasForRequiredFilterColumn($column)
    {
        list($key, $sub) = $this->splitFilterKey($column);
        if ($sub === null) {
            return $key;
        } else {
            $objectType = $key;
        }

        if ($objectType === $this->type) {
            list($key, $sub) = $this->splitFilterKey($sub);
            if ($sub === null) {
                return $key;
            }

            if ($key === 'vars') {
                return $this->joinVar($sub)->getJoinedVarAlias($sub);
            } else {
                throw new NotFoundError('Not yet, my type: %s - %s', $objectType, $key);
            }
        } else {
            throw new NotImplementedError('Not yet: %s - %s', $objectType, $sub);
        }
    }

    protected function splitFilterKey($key)
    {
        $dot = strpos($key, '.');
        if ($dot === false) {
            return [$key, null];
        } else {
            return [substr($key, 0, $dot), substr($key, $dot + 1)];
        }
    }

    protected function requireTable($name)
    {
        if ($alias = $this->getTableAliasFromQuery($name)) {
            return $alias;
        }

        $this->joinTable($name);
    }

    protected function joinTable($name)
    {
        if (!array_key_exists($name, $this->requiredTables)) {
            $alias = $this->makeAlias($name);
        }

        return $this->tableAliases($name);
    }

    protected function hasAlias($name)
    {
        return array_key_exists($name, $this->aliases);
    }

    protected function makeAlias($name)
    {
        if (substr($name, 0, 7) === 'icinga_') {
            $shortName = substr($name, 7);
        } else {
            $shortName = $name;
        }

        $parts = preg_split('/_/', $shortName, -1);
        $alias = '';
        foreach ($parts as $part) {
            $alias .= $part[0];
            if (! $this->hasAlias($alias)) {
                return $alias;
            }
        }

        $cnt = 1;
        do {
            $cnt++;
            if (! $this->hasAlias($alias . $cnt)) {
                return $alias . $cnt;
            }
        } while (! $this->hasAlias($alias));

        return $alias;
    }

    protected function getTableAliasFromQuery($table)
    {
        $tables = $this->query->getPart('from');
        $key = array_search($table, $tables);
        if ($key === null || $key === false) {
            return false;
        }
        /*
        'joinType'      => $type,
        'schema'        => $schema,
        'tableName'     => $tableName,
        'joinCondition' => $cond
        */
        return $key;
    }

    public function whereToSql($col, $sign, $expression)
    {
        return $this->connection->renderFilter(Filter::expression($col, $sign, $expression));
    }
}
