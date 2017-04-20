<?php

namespace Icinga\Module\Director\Web\Table;

use Icinga\Data\Filter\Filter;
use Icinga\Exception\IcingaException;
use Icinga\Module\Director\Db;
use Icinga\Module\Director\IcingaConfig\AssignRenderer;
use Icinga\Web\Widget\Tabs;
use ipl\Html\Icon;
use ipl\Html\Link;
use ipl\Html\Table;
use ipl\Translation\TranslationHelper;
use ipl\Web\Url;

class ServiceApplyRulesTable extends Table
{
    use TranslationHelper;

    protected $defaultAttributes = [
        'class' => ['simple', 'common-table', 'table-row-selectable'],
        'data-base-target' => '_next',
    ];

    private $db;

    public function __construct(Db $connection)
    {
        $this->db = $connection->getDbAdapter();
        $this->header();
        $this->fetchRows();
    }

    public function getColumnsToBeRendered()
    {
        return ['Service Name', 'assign where', 'Actions'];
    }

    public function renderRow($row)
    {
        $url = Url::fromPath('director/service/edit', [
            'id' => $row->id,
        ]);

        return static::tr([
            Table::td(Link::create($row->service, $url)),
            Table::td($this->renderApplyFilter($row->assign_filter)),
            Table::td($this->createActionLinks($row))->setSeparator(' ')
        ]);
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
        $links = [];
        $links[] = Link::create(
            Icon::create('sitemap'),
            'director/servicetemplate/applytargets',
            ['id' => $row->id],
            ['title' => $this->translate('Show affected Hosts')]
        );

        $links[] = Link::create(
            Icon::create('edit'),
            'director/service/edit',
            ['id' => $row->id],
            ['title' => $this->translate('Modify this Apply Rule')]
        );

        $links[] = Link::create(
            Icon::create('doc-text'),
            'director/service/render',
            ['id' => $row->id],
            ['title' => $this->translate('Apply Rule rendering preview')]
        );

        $links[] = Link::create(
            Icon::create('history'),
            'director/service/history',
            ['id' => $row->id],
            ['title' => $this->translate('Apply rule history')]
        );

        return $links;
    }

    protected function fetchRows()
    {
        $body = $this->body();
        foreach ($this->fetch() as $row) {
            $body->add($this->renderRow($row));
        }
    }

    public function fetch()
    {
        return $this->db->fetchAll(
            $this->prepareQuery()
        );
    }

    public function prepareQuery()
    {
        $columns = [
            'id'            => 's.id',
            'service'       => 's.object_name',
            'assign_filter' => 's.assign_filter',
        ];
        $query = $this->db->select()->from(
            ['s' => 'icinga_service'],
            $columns
        )->where(
            "object_type = 'apply'"
        )->where('service_set_id IS NULL')->order('s.object_name');

        return $query;
    }
}
