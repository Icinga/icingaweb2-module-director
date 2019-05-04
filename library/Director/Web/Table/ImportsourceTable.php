<?php

namespace Icinga\Module\Director\Web\Table;

use dipl\Html\Link;
use dipl\Web\Table\ZfQueryBasedTable;

class ImportsourceTable extends ZfQueryBasedTable
{
    protected $searchColumns = [
        'source_name',
        'description',
    ];

    public function getColumnsToBeRendered()
    {
        return [
            $this->translate('Source name'),
        ];
    }

    protected function assemble()
    {
        $this->getAttributes()->add('class', 'syncstate');
        parent::assemble();
    }

    public function renderRow($row)
    {
        $caption = [Link::create(
            $row->source_name,
            'director/importsource',
            ['id' => $row->id]
        )];
        if ($row->description !== null) {
            $caption[] = ': ' . $row->description;
        }

        if ($row->import_state === 'failing' && $row->last_error_message) {
            $caption[] = ' (' . $row->last_error_message . ')';
        }

        $tr = $this::row([$caption]);
        $tr->getAttributes()->add('class', $row->import_state);

        return $tr;
    }

    public function prepareQuery()
    {
        return $this->db()->select()->from(
            ['s' => 'import_source'],
            [
                'id'                 => 's.id',
                'source_name'        => 's.source_name',
                'provider_class'     => 's.provider_class',
                'import_state'       => 's.import_state',
                'last_error_message' => 's.last_error_message',
                'description'        => 's.description',
            ]
        )->order('source_name ASC');
    }
}
