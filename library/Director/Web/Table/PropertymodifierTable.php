<?php

namespace Icinga\Module\Director\Web\Table;

use Error;
use Exception;
use GuzzleHttp\Psr7\ServerRequest;
use Icinga\Module\Director\Hook\ImportSourceHook;
use Icinga\Module\Director\Objects\ImportSource;
use gipfl\IcingaWeb2\Link;
use gipfl\IcingaWeb2\Table\Extension\ZfSortablePriority;
use gipfl\IcingaWeb2\Table\ZfQueryBasedTable;
use gipfl\IcingaWeb2\Url;
use Icinga\Module\Director\Web\Form\PropertyTableSortForm;
use Icinga\Module\Director\Web\Form\QuickForm;
use ipl\Html\Form;
use ipl\Html\HtmlString;

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

    protected $readOnly = false;

    public static function load(ImportSource $source, Url $url)
    {
        $table = new static($source->getConnection());
        $table->source = $source;
        $table->url = $url;
        return $table;
    }

    public function setReadOnly($readOnly = true)
    {
        $this->readOnly = $readOnly;
        return $this;
    }

    public function render()
    {
        if ($this->readOnly || $this->request === null) {
            return parent::render();
        }

        return (new PropertyTableSortForm($this->getUniqueFormName(), new HtmlString(parent::render())))
            ->setAction($this->request->getUrl()->getAbsoluteUrl())
            ->on(Form::ON_SENT, function (PropertyTableSortForm $form) {
                $csrf = $form->getElement(QuickForm::CSRF);
                if ($csrf !== null && $csrf->isValid()) {
                    $this->reallyHandleSortPriorityActions();
                }
            })
            ->handleRequest(ServerRequest::fromGlobals())
            ->render();
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
                $hook = new $class();
                $caption .= ': ' . $hook->getName();
            } catch (Exception $e) {
                $caption = $this->createErrorCaption($caption, $e);
            } catch (Error $e) {
                $caption = $this->createErrorCaption($caption, $e);
            }
        } else {
            $caption .= ': ' . $row->description;
        }

        $renderedRow = $this::row([
            Link::create($caption, 'director/importsource/editmodifier', [
                'id'        => $row->id,
                'source_id' => $row->source_id,
            ]),
        ]);
        if ($this->readOnly) {
            return $renderedRow;
        }

        return $this->addSortPriorityButtons(
            $renderedRow,
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
        if ($this->readOnly) {
            return [$this->translate('Property')];
        }
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
