<?php

namespace Icinga\Module\Director\Web\Table;

use Icinga\Authentication\Auth;
use Icinga\Module\Director\Db;
use gipfl\IcingaWeb2\Link;
use gipfl\IcingaWeb2\Table\ZfQueryBasedTable;
use gipfl\IcingaWeb2\Url;
use Icinga\Module\Director\Db\IcingaObjectFilterHelper;
use Icinga\Module\Director\Objects\IcingaObject;
use Icinga\Module\Director\Restriction\FilterByNameRestriction;

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
            $inheritance
        );

        return $this;
    }


    public function renderRow($row)
    {
        $url = Url::fromPath('director/service/edit', [
            'name' => $row->object_name,
            'id'   => $row->id,
        ]);

        return static::tr([
            static::td([
                Link::create($row->service_set, $url),
            ]),
            static::td($row->object_name),
        ]);
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

        $query
            ->where('o.object_type = ?', 'object')
            ->order('os.object_name');

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
