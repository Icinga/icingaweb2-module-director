<?php

namespace Icinga\Module\Director\Web\Table;

use dipl\Html\BaseHtmlElement;
use dipl\Html\Html;
use dipl\Html\Link;
use dipl\Html\Table;
use dipl\Translation\TranslationHelper;
use dipl\Web\Url;

class CoreApiFieldsTable extends Table
{
    use TranslationHelper;

    protected $defaultAttributes = [
        'class' => ['common-table'/*, 'table-row-selectable'*/],
        //'data-base-target' => '_next',
    ];

    protected $fields;

    /** @var Url */
    protected $url;

    public function __construct($fields, Url $url)
    {
        $this->url = $url;
        $this->fields = $fields;
    }

    public function assemble()
    {
        $this->header();
        $body = $this->body();
        foreach ($this->fields as $name => $field) {
            $tr = $this::tr([
                $this::td($name),
                $this::td(Link::create(
                    $field->type,
                    $this->url->with('type', $field->type)
                )),
                $this::td($field->id)
                // $this::td($field->array_rank),
                // $this::td($this->renderKeyValue($field->attributes))
            ]);
            $this->addAttributeColumns($tr, $field->attributes);
            $body->add($tr);
        }
    }

    protected function addAttributeColumns(BaseHtmlElement $tr, $attrs)
    {
        $tr->add([
            $this->makeBooleanColumn($attrs->state),
            $this->makeBooleanColumn($attrs->config),
            $this->makeBooleanColumn($attrs->required),
            $this->makeBooleanColumn($attrs->no_user_modify),
            $this->makeBooleanColumn($attrs->no_user_view),
            $this->makeBooleanColumn($attrs->navigation),
        ]);
    }

    protected function makeBooleanColumn($value)
    {
        return $this::td($value ? Html::tag('strong', 'true') : 'false');
    }

    public function getColumnsToBeRendered()
    {
        return [
            $this->translate('Name'),
            $this->translate('Type'),
            $this->translate('Id'),
            // $this->translate('Array Rank'),
            // $this->translate('Attributes')
            $this->translate('State'),
            $this->translate('Config'),
            $this->translate('Required'),
            $this->translate('Protected'),
            $this->translate('Hidden'),
            $this->translate('Nav'),
        ];
    }

    protected function renderKeyValue($values)
    {
        $parts = [];
        foreach ((array) $values as $key => $value) {
            if (is_bool($value)) {
                $value = $value ? 'true' : 'false';
            }
            $parts[] = "$key: $value";
        }

        return implode(', ', $parts);
    }
}
