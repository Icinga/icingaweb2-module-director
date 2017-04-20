<?php

namespace Icinga\Module\Director\Web\Table;

use Icinga\Module\Director\Db;
use ipl\Html\Icon;
use ipl\Html\Link;
use ipl\Html\Table;
use ipl\Translation\TranslationHelper;
use ipl\Web\Url;

class ServiceTemplatesTable extends Table
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
        return ['Template name', 'Actions'];
    }

    public function renderRow($row)
    {
        $url = Url::fromPath('director/service/edit', [
            'name' => $row->service,
        ]);

        return static::tr([
            Table::td(Link::create($row->service, $url)),
            Table::td($this->createActionLinks($row))->setSeparator(' ')
        ]);
    }

    public function createActionLinks($row)
    {
        $links = [];
        $links[] = Link::create(
            Icon::create('sitemap'),
            'director/servicetemplate/usage',
            ['name' => $row->service],
            ['title' => $this->translate('Show template usage')]
        );

        $links[] = Link::create(
            Icon::create('edit'),
            'director/service/edit',
            ['name' => $row->service],
            ['title' => $this->translate('Modify this template')]
        );

        $links[] = Link::create(
            Icon::create('doc-text'),
            'director/service/render',
            ['name' => $row->service],
            ['title' => $this->translate('Template rendering preview')]
        );

        $links[] = Link::create(
            Icon::create('history'),
            'director/service/history',
            ['name' => $row->service],
            ['title' => $this->translate('Template history')]
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
            'service' => 's.object_name',
            'id'      => 's.id',
        ];
        $query = $this->db->select()->from(
            ['s' => 'icinga_service'],
            $columns
        )->where(
            "object_type = 'template'"
        )->order('s.object_name');

        return $query;
    }
}
