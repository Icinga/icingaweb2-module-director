<?php

namespace Icinga\Module\Director\Web\Table;

use Icinga\Authentication\Auth;
use Icinga\Module\Director\Db;
use gipfl\IcingaWeb2\Link;
use gipfl\IcingaWeb2\Table\ZfQueryBasedTable;
use gipfl\IcingaWeb2\Url;
use Icinga\Module\Director\Restriction\FilterByNameRestriction;

class ObjectSetTable extends ZfQueryBasedTable
{
    protected $searchColumns = [
        'os.object_name',
        'os.description',
        'os.assign_filter',
    ];

    private $type;

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
        return ['Name'];
    }

    public function renderRow($row)
    {
        $type = $this->getType();
        if ($row->object_type === 'apply') {
            $params['id'] = $row->id;
        } else {
            $params = array('name' => $row->object_name);
        }

        $url = Url::fromPath("director/${type}set", $params);

        return static::tr([
            static::td([
                Link::create(sprintf(
                    $this->translate('%s (%d members)'),
                    $row->object_name,
                    $row->count_services
                ), $url),
                $row->description ? ': ' . $row->description : null
            ])
        ]);
    }

    protected function prepareQuery()
    {
        $type = $this->getType();

        $columns = [
            'id'             => 'os.id',
            'object_name'    => 'os.object_name',
            'object_type'    => 'os.object_type',
            'assign_filter'  => 'os.assign_filter',
            'description'    => 'os.description',
            'count_services' => 'COUNT(DISTINCT o.id)',
        ];

        $query = $this->db()->select()->from(
            ['os' => "icinga_${type}_set"],
            $columns
        )->joinLeft(
            ['o' => "icinga_${type}"],
            "o.${type}_set_id = os.id",
            []
        );

        $nameFilter = new FilterByNameRestriction(
            $this->connection(),
            $this->auth,
            "${type}_set"
        );
        $nameFilter->applyToQuery($query, 'os');
        // Disabled for now, check for correctness:
        // $query->joinLeft(
        //     ['osi' => "icinga_${type}_set_inheritance"],
        //     "osi.parent_${type}_set_id = os.id",
        //     []
        // )->joinLeft(
        //     ['oso' => "icinga_${type}_set"],
        //     "oso.id = oso.${type}_set_id",
        //     []
        // );
        // 'count_hosts'    => 'COUNT(DISTINCT oso.id)',

        $query
            ->group('os.id')
            ->where('os.object_type = ?', 'template')
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
