<?php

namespace Icinga\Module\Director\Web\Table;

use Icinga\Authentication\Auth;
use Icinga\Module\Director\Db;
use gipfl\IcingaWeb2\Link;
use gipfl\IcingaWeb2\Table\ZfQueryBasedTable;
use gipfl\IcingaWeb2\Url;
use Icinga\Module\Director\Db\DbSelectParenthesis;
use Icinga\Module\Director\Db\DbUtil;
use Icinga\Module\Director\Db\IcingaObjectFilterHelper;
use Icinga\Module\Director\Objects\IcingaObject;
use Icinga\Module\Director\Restriction\FilterByNameRestriction;
use Ramsey\Uuid\Uuid;

class ObjectsTableSetMembers extends ZfQueryBasedTable
{
    use TableWithBranchSupport;

    protected $searchColumns = [
        'os.object_name',
        'o.object_name',
    ];

    private $type;

    /** @var IcingaObject */
    protected $dummyObject;

    protected $baseObjectUrl;

    /** @var Auth */
    private $auth;

    public static function create($type, Db $db, Auth $auth)
    {
        $table = new static($db);
        $table->type = $type;
        $table->auth = $auth;
        return $table;
    }

    public function getType()
    {
        return $this->type;
    }

    public function getColumnsToBeRendered()
    {
        return [
            'os.object_name' => 'Service Set',
            'o.object_name'  => 'Service Name'
        ];
    }

    public function setBaseObjectUrl($url)
    {
        $this->baseObjectUrl = $url;

        return $this;
    }

    protected function getRowClasses($row)
    {
        // TODO: remove isset, to figure out where it is missing
        if (isset($row->branch_uuid) && $row->branch_uuid !== null) {
            return ['branch_modified'];
        }
        return [];
    }

    /**
     * Should be triggered from renderRow, still unused.
     *
     * @param IcingaObject $template
     * @param string $inheritance
     * @return $this
     * @throws \Icinga\Exception\ProgrammingError
     */
    public function filterTemplate(
        IcingaObject $template,
        $inheritance = IcingaObjectFilterHelper::INHERIT_DIRECT
    ) {
        IcingaObjectFilterHelper::filterByTemplate(
            $this->getQuery(),
            $template,
            'o',
            $inheritance,
            $this->branchUuid
        );

        return $this;
    }


    public function renderRow($row)
    {
        $url = Url::fromPath('director/service/edit', [
            'name' => $row->object_name,
            'uuid' => Uuid::fromBytes(DbUtil::binaryResult($row->uuid))->toString(),
        ]);

        return static::tr([
            static::td([
                Link::create($row->service_set, $url),
            ]),
            static::td($row->object_name),
        ])->addAttributes(['class' => $this->getRowClasses($row)]);
    }

    /**
     * @return IcingaObject
     */
    protected function getDummyObject()
    {
        if ($this->dummyObject === null) {
            $type = $this->type;
            $this->dummyObject = IcingaObject::createByType($type);
        }
        return $this->dummyObject;
    }

    protected function prepareQuery()
    {
        $table = $this->getDummyObject()->getTableName();
        $type = $this->getType();

        $columns = [
            'id'             => 'o.id',
            'uuid'           => 'o.uuid',
            'service_set'    => 'os.object_name',
            'object_name'    => 'o.object_name',
            'object_type'    => 'os.object_type',
            'assign_filter'  => 'os.assign_filter',
            'description'    => 'os.description',
        ];

        $query = $this->db()->select()->from(
            ['o' => $table],
            $columns
        )->joinLeft(
            ['os' => "icinga_{$type}_set"],
            "o.{$type}_set_id = os.id",
            []
        )->where('o.host_id IS NULL');

        $nameFilter = new FilterByNameRestriction(
            $this->connection(),
            $this->auth,
            "{$type}_set"
        );
        $nameFilter->applyToQuery($query, 'os');

        if ($this->branchUuid) {
            $columns['branch_uuid'] = 'bos.branch_uuid';
            $conn = $this->connection();
            if ($conn->isPgsql()) {
                $columns['imports'] = 'CONCAT(\'[\', ARRAY_TO_STRING(ARRAY_AGG'
                    . '(CONCAT(\'"\', sub_o.object_name, \'"\')), \',\'), \']\')';
            } else {
                $columns['imports'] = 'CONCAT(\'[\', '
                    . 'GROUP_CONCAT(CONCAT(\'"\', sub_o.object_name, \'"\')), \']\')';
            }

            $columns = $this->branchifyColumns($columns);
            $this->stripSearchColumnAliases();

            $query->reset('columns');
            $right = clone($query);
            $conn = $this->connection();

            $query->columns($columns)->joinLeft(
                ['bos' => "branched_icinga_{$type}_set"],
                // TODO: PgHexFunc
                $this->db()->quoteInto(
                    'bos.uuid = os.uuid AND bos.branch_uuid = ?',
                    $conn->quoteBinary($this->branchUuid->getBytes())
                ),
                []
            )->joinLeft(
                ['oi' => $table . '_inheritance'],
                'o.id = oi.' . $this->getType() . '_id',
                []
            )->joinLeft(
                ['sub_o' => $table],
                'sub_o.id = oi.parent_' . $this->getType() . '_id',
                []
            )->where("(bos.branch_deleted IS NULL OR bos.branch_deleted = 'n')");

            $columns['imports'] = 'bo.imports';
            $right->columns($columns)->joinRight(
                ['bos' => "branched_icinga_{$type}_set"],
                'bos.uuid = os.uuid',
                []
            )
                ->where('os.uuid IS NULL')
                ->where('bos.branch_uuid = ?', $conn->quoteBinary($this->branchUuid->getBytes()));
            $query->group('COALESCE(os.uuid, bos.uuid)');
            $right->group('COALESCE(os.uuid, bos.uuid)');
            if ($conn->isPgsql()) {
                // This is ugly, might want to modify the query - even a subselect looks better
                $query->group('bos.uuid')->group('os.uuid')->group('os.id')->group('bos.branch_uuid')->group('o.id');
                $right->group('bos.uuid')->group('os.uuid')->group('os.id')->group('bos.branch_uuid')->group('o.id');
            }
            $right->joinLeft(
                ['bo' => "branched_icinga_{$type}"],
                "bo.{$type}_set = bos.object_name",
                []
            )->group(['bo.object_name', 'o.object_name', 'bo.uuid', 'bo.imports']);
            $query->joinLeft(
                ['bo' => "branched_icinga_{$type}"],
                "bo.{$type}_set = bos.object_name",
                []
            )->group(['bo.object_name', 'o.object_name', 'bo.uuid']);

            $query = $this->db()->select()->union([
                'l' => new DbSelectParenthesis($query),
                'r' => new DbSelectParenthesis($right),
            ]);
            $query = $this->db()->select()->from(['u' => $query]);
            $query->order('object_name')->limit(100);

            $query
                ->group('uuid')
                ->where('object_type = ?', 'template')
                ->order('object_name');
            if ($conn->isPgsql()) {
                $query
                    ->group('uuid')
                    ->group('id')
                    ->group('imports')
                    ->group('branch_uuid')
                    ->group('object_name')
                    ->group('object_type')
                    ->group('assign_filter')
                    ->group('description')
                    ->group('service_set');
            }
        } else {
            $query
                ->where('o.object_type = ?', 'object')
                ->order('os.object_name');
        }

        return $query;
    }

    /**
     * @return Db
     */
    public function connection()
    {
        return parent::connection();
    }
}
