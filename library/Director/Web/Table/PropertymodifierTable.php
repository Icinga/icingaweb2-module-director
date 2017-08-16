<?php

namespace Icinga\Module\Director\Web\Table;

use Icinga\Module\Director\Objects\ImportSource;
use ipl\Html\Link;
use ipl\Web\Table\ZfQueryBasedTable;

class PropertymodifierTable extends ZfQueryBasedTable
{
    /** @var ImportSource */
    protected $source;

    public static function load(ImportSource $source)
    {
        $table = new static($source->getConnection());
        $table->source = $source;
        return $table;
    }

    protected function assemble()
    {
        $this->attributes()->set('data-base-target', '_self');
    }

    public function getColumns()
    {
        return array(
            'id'              => 'm.id',
            'source_id'       => 'm.source_id',
            'property_name'   => 'm.property_name',
            'target_property' => 'm.target_property',
            'description'     => 'm.description',
            'provider_class'  => 'm.provider_class',
            'priority'        => 'm.priority',
        );
    }

    public function renderRow($row)
    {
        $caption = $row->property_name;
        if ($row->target_property !== null) {
            $caption .= ' -> ' . $row->target_property;
        }
        if ($row->description !== null) {
            $caption .= ': ' . $row->description;
        }

        return $this::row([
            Link::create(
                $caption,
                'director/importsource/editmodifier',
                [
                    'id'        => $row->id,
                    'source_id' => $row->source_id,
                ]
            )
        ]);
    }

    public function getColumnsToBeRendered()
    {
        return [
            $this->translate('Property'),
        ];
    }

    public function prepareQuery()
    {
        return $this->db()->select()->from(
            ['m' => 'import_row_modifier'],
            $this->getColumns()
        )->where('m.source_id = ?', $this->source->getId())
        ->order('priority');
    }
}
