<?php

namespace Icinga\Module\Director\Web\Table;

use Icinga\Authentication\Auth;
use Icinga\Data\Filter\Filter;
use Icinga\Exception\IcingaException;
use Icinga\Module\Director\Db;
use Icinga\Module\Director\Db\DbSelectParenthesis;
use Icinga\Module\Director\Db\DbUtil;
use Icinga\Module\Director\Db\IcingaObjectFilterHelper;
use Icinga\Module\Director\IcingaConfig\AssignRenderer;
use Icinga\Module\Director\Objects\IcingaObject;
use gipfl\IcingaWeb2\Icon;
use gipfl\IcingaWeb2\Link;
use gipfl\IcingaWeb2\Table\ZfQueryBasedTable;
use gipfl\IcingaWeb2\Url;
use gipfl\IcingaWeb2\Zf1\Db\FilterRenderer;
use Ramsey\Uuid\Uuid;
use Zend_Db_Select as ZfSelect;

class ApplyRulesTable extends ZfQueryBasedTable
{
    use TableWithBranchSupport;

    protected $searchColumns = [
        'o.object_name',
        'o.assign_filter',
    ];

    private $type;

    /** @var IcingaObject */
    protected $dummyObject;

    protected $baseObjectUrl;

    protected $linkWithName = false;

    public static function create($type, Db $db)
    {
        $table = new static($db);
        $table->setType($type);
        return $table;
    }

    public function setType($type)
    {
        $this->type = $type;

        return $this;
    }

    public function setBaseObjectUrl($url)
    {
        $this->baseObjectUrl = $url;

        return $this;
    }

    public function createLinksWithNames($linksWithName = true)
    {
        $this->linkWithName = (bool) $linksWithName;

        return $this;
    }

    public function getType()
    {
        return $this->type;
    }

    public function getColumnsToBeRendered()
    {
        return ['Name', 'assign where'/*, 'Actions'*/];
    }

    public function renderRow($row)
    {
        $row->uuid = DbUtil::binaryResult($row->uuid);
        if ($this->linkWithName) {
            $params = ['name' => $row->object_name];
        } else {
            $params = ['uuid' => Uuid::fromBytes($row->uuid)->toString()];
        }
        $url = Url::fromPath("director/{$this->baseObjectUrl}/edit", $params);

        $assignWhere = $this->renderApplyFilter($row->assign_filter);

        if (! empty($row->apply_for)) {
            $assignWhere = sprintf('apply for %s / %s', $row->apply_for, $assignWhere);
        }

        $tr = static::tr([
            static::td(Link::create($row->object_name, $url)),
            static::td($assignWhere),
            // NOT (YET) static::td($this->createActionLinks($row))->setSeparator(' ')
        ]);

        $classes = $this->getRowClasses($row);

        if ($row->disabled === 'y') {
            $classes[] = 'disabled';
        }

        $tr->getAttributes()->add('class', $classes);

        return $tr;
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

    protected function renderApplyFilter($assignFilter)
    {
        try {
            $string = AssignRenderer::forFilter(
                Filter::fromQueryString($assignFilter)
            )->renderAssign();
            // Do not prefix it
            $string = preg_replace('/^assign where /', '', $string);
        } catch (IcingaException $e) {
            // ignore errors in filter rendering
            $string = 'Error in Filter rendering: ' . $e->getMessage();
        }

        return $string;
    }

    public function createActionLinks($row)
    {
        $params = ['uuid' => Uuid::fromBytes($row->uuid)->toString()];
        $baseUrl = 'director/' . $this->baseObjectUrl;
        $links = [];
        $links[] = Link::create(
            Icon::create('sitemap'),
            "{$baseUrl}template/applytargets",
            ['id' => $row->id],
            ['title' => $this->translate('Show affected Objects')]
        );

        $links[] = Link::create(
            Icon::create('edit'),
            "$baseUrl/edit",
            $params,
            ['title' => $this->translate('Modify this Apply Rule')]
        );

        $links[] = Link::create(
            Icon::create('doc-text'),
            "$baseUrl/render",
            $params,
            ['title' => $this->translate('Apply Rule rendering preview')]
        );

        $links[] = Link::create(
            Icon::create('history'),
            "$baseUrl/history",
            $params,
            ['title' => $this->translate('Apply rule history')]
        );

        return $links;
    }

    protected function applyRestrictions(ZfSelect $query)
    {
        $auth = Auth::getInstance();
        $type = $this->type;
        // TODO: Centralize this logic
        if ($type === 'scheduledDowntime') {
            $type = 'scheduled-downtime';
        }
        $restrictions = $auth->getRestrictions("director/$type/apply/filter-by-name");
        if (empty($restrictions)) {
            return $query;
        }

        $filter = Filter::matchAny();
        foreach ($restrictions as $restriction) {
            $filter->addFilter(Filter::where('o.object_name', $restriction));
        }

        return FilterRenderer::applyToQuery($filter, $query);
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

    public function prepareQuery()
    {
        $table = $this->getDummyObject()->getTableName();
        $columns = [
            'id'            => 'o.id',
            'uuid'          => 'o.uuid',
            'object_name'   => 'o.object_name',
            'object_type'   => 'o.object_type',
            'disabled'      => 'o.disabled',
            'assign_filter' => 'o.assign_filter',
            'apply_for'     => '(NULL)',
        ];

        if ($table === 'icinga_service') {
            $columns['apply_for'] = 'o.apply_for';
        }

        $conn = $this->connection();
        $query = $this->db()->select()->from(
            ['o' => $table],
            $columns
        )->order('o.object_name');

        if ($this->branchUuid) {
            $columns = $this->branchifyColumns($columns);
            $columns['branch_uuid'] = 'bo.branch_uuid';
            if ($conn->isPgsql()) {
                $columns['imports'] = 'CONCAT(\'[\', ARRAY_TO_STRING(ARRAY_AGG'
                    . '(CONCAT(\'"\', sub_o.object_name, \'"\')), \',\'), \']\')';
            } else {
                $columns['imports'] = 'CONCAT(\'[\', '
                    . 'GROUP_CONCAT(CONCAT(\'"\', sub_o.object_name, \'"\')), \']\')';
            }

            $this->stripSearchColumnAliases();

            $query->reset('columns');
            $right = clone($query);

            $query->columns($columns)
                ->joinLeft(
                    ['oi' => $table . '_inheritance'],
                    'o.id = oi.' . $this->getType() . '_id',
                    []
                )->joinLeft(
                    ['sub_o' => $table],
                    'sub_o.id = oi.parent_' . $this->getType() . '_id',
                    []
                )->group(['o.id', 'bo.uuid', 'bo.branch_uuid']);

            $query->joinLeft(
                ['bo' => "branched_$table"],
                // TODO: PgHexFunc
                $this->db()->quoteInto(
                    'bo.uuid = o.uuid AND bo.branch_uuid = ?',
                    DbUtil::quoteBinaryLegacy($this->branchUuid->getBytes(), $this->db())
                ),
                []
            )->where("(bo.branch_deleted IS NULL OR bo.branch_deleted = 'n')");

            if ($this->type === 'service') {
                $query->where('o.service_set_id IS NULL AND bo.service_set IS NULL');
            }

            $columns['imports'] = 'bo.imports';

            $right->columns($columns)
                ->joinRight(
                    ['bo' => "branched_$table"],
                    'bo.uuid = o.uuid',
                    []
                )
                ->where('o.uuid IS NULL')
                ->where('bo.branch_uuid = ?', $conn->quoteBinary($this->branchUuid->getBytes()));

            $query = $this->db()->select()->union([
                'l' => new DbSelectParenthesis($query),
                'r' => new DbSelectParenthesis($right),
            ]);

            $query = $this->db()->select()->from(['u' => $query]);
            $query->order('object_name')->limit(100);
        } else {
            if ($this->type === 'service') {
                $query->where('service_set_id IS NULL');
            }
        }

        $query->where(
            "object_type = 'apply'"
        );

        $this->applyRestrictions($query);

        return $this->applyRestrictions($query);
    }

    /**
     * @return Db
     */
    public function connection()
    {
        return parent::connection();
    }
}
