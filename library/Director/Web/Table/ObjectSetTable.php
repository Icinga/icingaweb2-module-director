<?php

namespace Icinga\Module\Director\Web\Table;

use gipfl\IcingaWeb2\Zf1\Db\FilterRenderer;
use Icinga\Authentication\Auth;
use Icinga\Data\Filter\Filter;
use Icinga\Module\Director\Db;
use gipfl\IcingaWeb2\Link;
use gipfl\IcingaWeb2\Table\ZfQueryBasedTable;
use gipfl\IcingaWeb2\Url;
use Icinga\Module\Director\Restriction\FilterByNameRestriction;
use ipl\Html\Html;
use Ramsey\Uuid\Uuid;

class ObjectSetTable extends ZfQueryBasedTable
{
    protected $searchColumns = [
        'os.object_name',
        'os.description',
        'os.assign_filter',
        'o.object_name',
    ];

    private $type;

    /** @var Auth */
    private $auth;

    protected $queries = [];

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
        return [$this->translate('Name')];
    }

    public function renderRow($row)
    {
        $type = $this->getType();
        $params = [
            'uuid' => Uuid::fromBytes(Db\DbUtil::binaryResult($row->uuid))->toString(),
        ];

        $url = Url::fromPath("director/{$type}set", $params);

        $classes = $this->getRowClasses($row);
        $tr = static::tr([
            static::td([
                Link::create(sprintf(
                    $this->translate('%s (%d members)'),
                    $row->object_name,
                    $row->count_services
                ), $url),
                $row->description ? [Html::tag('br'), Html::tag('i', $row->description)] : null
            ])
        ]);
        if (! empty($classes)) {
            $tr->getAttributes()->add('class', $classes);
        }

        return $tr;
    }

    protected function getRowClasses($row)
    {
        return [];
    }

    protected function prepareQuery()
    {
        $type = $this->getType();

        $table = "icinga_{$type}_set";
        $columns = [
            'id'             => 'os.id',
            'uuid'           => 'os.uuid',
            'object_name'    => 'os.object_name',
            'object_type'    => 'os.object_type',
            'assign_filter'  => 'os.assign_filter',
            'description'    => 'os.description',
            'count_services' => 'COUNT(DISTINCT o.uuid)',
        ];

        $query = $this->db()->select()->from(
            ['os' => $table],
            $columns
        )->joinLeft(
            ['o' => "icinga_{$type}"],
            "o.{$type}_set_id = os.id",
            []
        );

        $nameFilter = new FilterByNameRestriction(
            $this->connection(),
            $this->auth,
            "{$type}_set"
        );
        $nameFilter->applyToQuery($query, 'os');
        /** @var Db $conn */
        $conn = $this->connection();

        // Disabled for now, check for correctness:
        // $query->joinLeft(
        //     ['osi' => "icinga_{$type}_set_inheritance"],
        //     "osi.parent_{$type}_set_id = os.id",
        //     []
        // )->joinLeft(
        //     ['oso' => "icinga_{$type}_set"],
        //     "oso.id = oso.{$type}_set_id",
        //     []
        // );
        // 'count_hosts'    => 'COUNT(DISTINCT oso.id)',

        $query
            ->group('os.uuid')
            ->where('os.object_type = ?', 'template')
            ->order('os.object_name');
        if ($conn->isPgsql()) {
            // BS. Drop count? Sub-select? Better query?
            $query
                ->group('os.uuid')
                ->group('os.id')
                ->group('os.object_name')
                ->group('os.object_type')
                ->group('os.assign_filter')
                ->group('os.description');
        };
        $this->queries = [$query];

        return $query;
    }

    public function search($search)
    {
        if (! empty($search)) {
            $columns = $this->getSearchColumns();
            if (strpos($search, ' ') === false) {
                $filter = Filter::matchAny();
                foreach ($columns as $column) {
                    $filter->addFilter(Filter::expression($column, '=', "*$search*"));
                }
            } else {
                $filter = Filter::matchAll();
                foreach (explode(' ', $search) as $s) {
                    $sub = Filter::matchAny();
                    foreach ($columns as $column) {
                        $sub->addFilter(Filter::expression($column, '=', "*$s*"));
                    }
                    $filter->addFilter($sub);
                }
            }

            foreach ($this->queries as $query) {
                FilterRenderer::applyToQuery($filter, $query);
            }
        }

        return $this;
    }

    /**
     * @return Db
     */
    public function connection()
    {
        return parent::connection();
    }
}
