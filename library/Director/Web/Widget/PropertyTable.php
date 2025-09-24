<?php

namespace Icinga\Module\Director\Web\Widget;

use ipl\Html\HtmlElement;
use ipl\Html\Table;
use ipl\I18n\Translation;
use ipl\Web\Url;
use ipl\Web\Widget\Link;
use Ramsey\Uuid\Uuid;

class PropertyTable extends Table
{
    use Translation;

    protected $defaultAttributes = [
        'class' => 'common-table table-row-selectable property-table',
        'data-base-target' => '_next',
    ];

    public function __construct(
        protected array $properties,
        protected bool $isFieldsTable = false
    ) {
    }

    protected function assemble()
    {
        foreach ($this->properties as $property) {
            $url = Url::fromPath(
                'director/property',
                ['uuid' => Uuid::fromBytes($property->uuid)->toString()]
            );

            if (isset($property->parent_uuid)) {
                $url->addParams(['parent_uuid' => Uuid::fromBytes($property->parent_uuid)->toString()]);
            }

            $columns = [
                static::td([HtmlElement::create('strong', null, new Link($property->key_name, $url))])
                      ->setSeparator(' '),
                static::td([HtmlElement::create('p', null, $property->label)])->setSeparator(' '),
                static::td([HtmlElement::create('p', null, $property->value_type)]),
            ];

            if (isset($property->used_count) && $property->used_count > 0) {
                $columns[] = static::td([HtmlElement::create('p', null, $this->translate('In use'))]);
            } else {
                $columns[] = static::td([HtmlElement::create('p', null, $this->translate('Not in use'))]);
            }

            $this->addHtml(static::tr($columns));
        }
    }
}
