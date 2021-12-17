<?php

namespace Icinga\Module\Director\Web\Table;

use Icinga\Authentication\Auth;
use Icinga\Module\Director\Db;
use Icinga\Module\Director\Db\DbSelectParenthesis;
use Icinga\Module\Director\Db\IcingaObjectFilterHelper;
use Icinga\Module\Director\Objects\IcingaObject;
use Icinga\Module\Director\Restriction\FilterByNameRestriction;
use Icinga\Module\Director\Restriction\HostgroupRestriction;
use Icinga\Module\Director\Restriction\ObjectRestriction;
use gipfl\IcingaWeb2\Link;
use gipfl\IcingaWeb2\Table\ZfQueryBasedTable;
use gipfl\IcingaWeb2\Url;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use Zend_Db_Select as ZfSelect;

class ObjectsTable extends ZfQueryBasedTable
{
    /** @var ObjectRestriction[] */
    protected $objectRestrictions;

    protected $columns = [
        'object_name' => 'o.object_name',
        'object_type' => 'o.object_type',
        'disabled'    => 'o.disabled',
        'uuid'        => 'o.uuid',
    ];

    protected $searchColumns = ['o.object_name'];

    protected $showColumns = ['object_name' => 'Name'];

    protected $filterObjectType = 'object';

    protected $type;

    /** @var UuidInterface|null */
    protected $branchUuid;

    protected $baseObjectUrl;

    /** @var IcingaObject */
    protected $dummyObject;

    protected $leftSubQuery;

    protected $rightSubQuery;

    /** @var Auth */
    private $auth;

    /**
     * @param $type
     * @param Db $db
     * @return static
     */
    public static function create($type, Db $db)
    {
        $class = __NAMESPACE__ . '\\ObjectsTable' . ucfirst($type);
        if (! class_exists($class)) {
            $class = __CLASS__;
        }

        /** @var static $table */
        $table = new $class($db);
        $table->type = $type;
        return $table;
    }

    public function getType()
    {
        return $this->type;
    }

    /**
     * @param string $url
     * @return $this
     */
    public function setBaseObjectUrl($url)
    {
        $this->baseObjectUrl = $url;

        return $this;
    }

    /**
     * @return Auth
     */
    public function getAuth()
    {
        return $this->auth;
    }

    public function setAuth(Auth $auth)
    {
        $this->auth = $auth;
        return $this;
    }

    public function filterObjectType($type)
    {
        $this->filterObjectType = $type;
        return $this;
    }

    public function addObjectRestriction(ObjectRestriction $restriction)
    {
        $this->objectRestrictions[$restriction->getName()] = $restriction;
        return $this;
    }

    public function setBranchUuid(UuidInterface $uuid = null)
    {
        $this->branchUuid = $uuid;

        return $this;
    }

    public function getColumns()
    {
        return $this->columns;
    }

    public function getColumnsToBeRendered()
    {
        return $this->showColumns;
    }

    public function filterTemplate(
        IcingaObject $template,
        $inheritance = Db\IcingaObjectFilterHelper::INHERIT_DIRECT
    ) {
        IcingaObjectFilterHelper::filterByTemplate(
            $this->getQuery(),
            $template,
            'o',
            $inheritance
        );

        return $this;
    }

    protected function getMainLinkLabel($row)
    {
        return $row->object_name;
    }

    protected function renderObjectNameColumn($row)
    {
        $type = $this->baseObjectUrl;
        $url = Url::fromPath("director/${type}", [
            'uuid' => Uuid::fromBytes($row->uuid)->toString()
        ]);

        return static::td(Link::create($this->getMainLinkLabel($row), $url));
    }

    protected function renderExtraColumns($row)
    {
        $columns = $this->getColumnsToBeRendered();
        unset($columns['object_name']);
        $cols = [];
        foreach ($columns as $key => & $label) {
            $cols[] = static::td($row->$key);
        }

        return $cols;
    }

    public function renderRow($row)
    {
        if (isset($row->uuid) && is_resource($row->uuid)) {
            $row->uuid = stream_get_contents($row->uuid);
        }
        $tr = static::tr([
            $this->renderObjectNameColumn($row),
            $this->renderExtraColumns($row)
        ]);

        $classes = $this->getRowClasses($row);
        if ($row->disabled === 'y') {
            $classes[] = 'disabled';
        }
        if (! empty($classes)) {
            $tr->getAttributes()->add('class', $classes);
        }

        return $tr;
    }

    protected function getRowClasses($row)
    {
        // TODO: remove isset, to figure out where it is missing
        if (isset($row->branch_uuid) && $row->branch_uuid !== null) {
            return ['branch_modified'];
        }
        return [];
    }

    protected function applyObjectTypeFilter(ZfSelect $query, ZfSelect $right = null)
    {
        if ($right) {
            $right->where(
                'bo.object_type = ?',
                $this->filterObjectType
            );
        }
        return $query->where(
            'o.object_type = ?',
            $this->filterObjectType
        );
    }

    protected function applyRestrictions(ZfSelect $query)
    {
        foreach ($this->getRestrictions() as $restriction) {
            $restriction->applyToQuery($query);
        }

        return $query;
    }

    protected function getRestrictions()
    {
        if ($this->objectRestrictions === null) {
            $this->objectRestrictions = $this->loadRestrictions();
        }

        return $this->objectRestrictions;
    }

    protected function loadRestrictions()
    {
        /** @var Db $db */
        $db = $this->connection();
        $auth = $this->getAuth();

        return [
            new HostgroupRestriction($db, $auth),
            new FilterByNameRestriction($db, $auth, $this->getDummyObject()->getShortTableName())
        ];
    }

    /**
     * @return IcingaObject
     */
    protected function getDummyObject()
    {
        if ($this->dummyObject === null) {
            $type = $this->getType();
            $this->dummyObject = IcingaObject::createByType($type);
        }
        return $this->dummyObject;
    }

    protected function branchifyColumns($columns)
    {
        $result = [
            'uuid' => 'COALESCE(o.uuid, bo.uuid)'
        ];
        $ignore = ['o.id'];
        foreach ($columns as $alias => $column) {
            if (substr($column, 0, 2) === 'o.' && ! in_array($column, $ignore)) {
                // bo.column, o.column
                $column = "COALESCE(b$column, $column)";
            }

            // Used in Service Tables:
            if ($column === 'h.object_name' && $alias = 'host') {
                $column = "COALESCE(bo.host, $column)";
            }

            $result[$alias] = $column;
        }

        return $result;
    }

    protected function stripSearchColumnAliases()
    {
        foreach ($this->searchColumns as &$column) {
            $column = preg_replace('/^[a-z]+\./', '', $column);
        }
    }

    protected function prepareQuery()
    {
        $table = $this->getDummyObject()->getTableName();
        if ($this->branchUuid) {
            $this->columns['branch_uuid'] = 'bo.branch_uuid';
        }

        $columns = $this->getColumns();
        if ($this->branchUuid) {
            $columns = $this->branchifyColumns($columns);
            $this->stripSearchColumnAliases();
        }
        $query = $this->db()->select()->from(['o' => $table], $columns);

        if ($this->branchUuid) {
            $right = clone($query);
            // Hint: Right part has only those with object = null
            //       This means that restrictions on $right would hide all
            //       new rows. Dedicated restriction logic for the branch-only
            //       part of thw union are not required, we assume that restrictions
            //       for new objects have been checked once they have been created
            $query = $this->applyRestrictions($query);
            /** @var Db $conn */
            $conn = $this->connection();
            $query->joinLeft(
                ['bo' => "branched_$table"],
                // TODO: PgHexFunc
                $this->db()->quoteInto(
                    'bo.uuid = o.uuid AND bo.branch_uuid = ?',
                    $conn->quoteBinary($this->branchUuid->getBytes())
                ),
                []
            )->where("(bo.branch_deleted IS NULL OR bo.branch_deleted = 'n')");
            $this->applyObjectTypeFilter($query, $right);
            $right->joinRight(
                ['bo' => "branched_$table"],
                'bo.uuid = o.uuid',
                []
            )
            ->where('o.uuid IS NULL')
            ->where('bo.branch_uuid = ?', $conn->quoteBinary($this->branchUuid->getBytes()));
            $this->leftSubQuery = $query;
            $this->rightSubQuery = $right;
            $query = $this->db()->select()->union([
                'l' => new DbSelectParenthesis($query),
                'r' => new DbSelectParenthesis($right),
            ]);
            $query = $this->db()->select()->from(['u' => $query]);
            $query->order('object_name')->limit(100);
        } else {
            $this->applyObjectTypeFilter($query);
            $query->order('o.object_name')->limit(100);
        }

        return $query;
    }
}
