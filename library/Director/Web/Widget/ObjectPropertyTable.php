<?php

namespace Icinga\Module\Director\Web\Widget;

use ipl\Html\Html;
use ipl\Html\HtmlElement;
use ipl\Html\Table;
use ipl\I18n\Translation;
use ipl\Web\Url;
use ipl\Web\Widget\Link;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

class ObjectPropertyTable extends Table
{
    use Translation;

    protected $defaultAttributes = [
        'class' => 'common-table table-row-selectable object-property-table'
    ];

    public function __construct(
        protected UuidInterface $objectUuid,
        protected array $properties
    ) {
    }

    protected function assemble()
    {
        $this->add(static::tr([
            static::th([HtmlElement::create('p', null, $this->translate('Key'))])->setSeparator(' '),
            static::th([HtmlElement::create('p', null, $this->translate('Label'))])->setSeparator(' '),
            static::th([HtmlElement::create('p', null, $this->translate('Type'))]),
            static::th([HtmlElement::create('p', null, $this->translate('Mandatory'))])
        ]));
        foreach ($this->properties as $property) {
            $objectPropertyLink = new Link(
                $property->key_name,
                Url::fromPath(
                    'director/host/properties',
                    [
                        'uuid' => $this->objectUuid->toString(),
                        'property_uuid' => Uuid::fromBytes($property->uuid)->toString()
                    ]
                ),
                ['target' => '_blank']
            );

            $this->add(static::tr([
                static::td([HtmlElement::create('p', null, $objectPropertyLink)])->setSeparator(' '),
                static::td([HtmlElement::create('p', null, $property->label)])->setSeparator(' '),
                static::td([HtmlElement::create('p', null, $property->value_type)])->setSeparator(' '),
                static::td([HtmlElement::create('p', null, $property->required)])
            ]));
        }
    }
}
