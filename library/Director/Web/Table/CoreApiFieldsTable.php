<?php

namespace Icinga\Module\Director\Web\Table;

use ipl\Html\BaseHtmlElement;
use ipl\Html\Html;
use gipfl\IcingaWeb2\Link;
use ipl\Html\Table;
use ipl\I18n\Translation;
use gipfl\IcingaWeb2\Url;

class CoreApiFieldsTable extends Table
{
    use Translation;

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
        if (empty($this->fields)) {
            return;
        }
        $this->add(Html::tag('thead', Html::tag('tr', Html::wrapEach($this->getColumnsToBeRendered(), 'th'))));
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
            $this->add($tr);
        }
    }

    protected function addAttributeColumns(BaseHtmlElement $tr, $attrs)
    {
        $tr->add([
            $this->makeBooleanColumn($attrs->state),
            $this->makeBooleanColumn($attrs->config),
            $this->makeBooleanColumn($attrs->required),
            $this->makeBooleanColumn(isset($attrs->deprecated) ? $attrs->deprecated : null),
            $this->makeBooleanColumn($attrs->no_user_modify),
            $this->makeBooleanColumn($attrs->no_user_view),
            $this->makeBooleanColumn($attrs->navigation),
        ]);
    }

    protected function makeBooleanColumn($value)
    {
        if ($value === null) {
            return $this::td('-');
        }

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
            $this->translate('Deprecated'),
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
