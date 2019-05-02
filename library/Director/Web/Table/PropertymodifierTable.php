<?php

namespace Icinga\Module\Director\Web\Table;

use Error;
use Exception;
use Icinga\Module\Director\Hook\ImportSourceHook;
use Icinga\Module\Director\Objects\ImportSource;
use gipfl\IcingaWeb2\Link;
use gipfl\IcingaWeb2\Table\Extension\ZfSortablePriority;
use gipfl\IcingaWeb2\Table\ZfQueryBasedTable;
use gipfl\IcingaWeb2\Url;

class PropertymodifierTable extends ZfQueryBasedTable
{
    use ZfSortablePriority;

    protected $searchColumns = [
        'property_name',
        'target_property',
    ];

    /** @var ImportSource */
    protected $source;

    /** @var Url */
    protected $url;

    protected $keyColumn = 'id';

    protected $priorityColumn = 'priority';

    public static function load(ImportSource $source, Url $url)
    {
        $table = new static($source->getConnection());
        $table->source = $source;
        $table->url = $url;
        return $table;
    }

    public function render()
    {
        return $this->renderWithSortableForm();
    }

    protected function assemble()
    {
        $this->getAttributes()->set('data-base-target', '_self');
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
        if ($row->description === null) {
            $class = $row->provider_class;
            try {
                /** @var ImportSourceHook $hook */
                $hook = new $class;
                $caption .= ': ' . $hook->getName();
            } catch (Exception $e) {
                $caption = $this->createErrorCaption($caption, $e);
            } catch (Error $e) {
                $caption = $this->createErrorCaption($caption, $e);
            }
        } else {
            $caption .= ': ' . $row->description;
        }

        return $this->addSortPriorityButtons(
            $this::row([
                Link::create($caption, 'director/importsource/editmodifier', [
                    'id'        => $row->id,
                    'source_id' => $row->source_id,
                ]),
            ]),
            $row
        );
    }

    /**
     * @param $caption
     * @param Exception|Error $e
     * @return array
     */
    protected function createErrorCaption($caption, $e)
    {
        return [
            $caption,
            ': ',
            $this::tag('span', ['class' => 'error'], $e->getMessage())
        ];
    }

    public function getColumnsToBeRendered()
    {
        return [
            $this->translate('Property'),
            $this->getSortPriorityTitle()
        ];
    }

    public function prepareQuery()
    {
        return $this->db()->select()->from(
            ['m' => 'import_row_modifier'],
            $this->getColumns()
        )->where('m.source_id = ?', $this->source->get('id'))
        ->order('priority');
    }
}
