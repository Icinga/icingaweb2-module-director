<?php

namespace Icinga\Module\Director\Web\Table;

use Icinga\Module\Director\Db;
use ipl\Html\Link;
use ipl\Web\Url;

class ObjectSetTable extends QueryBasedTable
{
    protected $searchColumns = [
        'os.object_name',
        'os.description',
        'os.assign_filter',
    ];

    private $type;

    public static function create($type, Db $db)
    {
        $table = new static($db);
        $table->type = $type;
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
}
