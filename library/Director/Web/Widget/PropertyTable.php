<?php

namespace Icinga\Module\Director\Web\Widget;

use ipl\Html\HtmlElement;
use ipl\Html\Table;
use ipl\Web\Url;
use ipl\Web\Widget\Link;
use Ramsey\Uuid\Uuid;

class PropertyTable extends Table
{
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
            if ($this->isFieldsTable) {
                $url = Url::fromPath(
                    'director/property/edit-field',
                    [
                        'uuid' => Uuid::fromBytes($property->uuid)->toString(),
                        'parent_uuid' => Uuid::fromBytes($property->parent_uuid)->toString()
                    ]
                );
            } else {
                $url = Url::fromPath(
                    'director/property',
                    ['uuid' => Uuid::fromBytes($property->uuid)->toString()]
                );
            }

            $this->add(static::tr([
                static::td([HtmlElement::create('strong', null, new Link($property->key_name, $url))])
                    ->setSeparator(' '),
                static::td([HtmlElement::create('p', null, $property->label)])->setSeparator(' '),
                static::td([HtmlElement::create('p', null, $property->value_type)])
            ]));
        }
    }
}
