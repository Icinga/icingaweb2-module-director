<?php

namespace Icinga\Module\Director\Web\Widget;

use Icinga\Module\Director\Db\DbUtil;
use ipl\Html\HtmlElement;
use ipl\Html\Table;
use ipl\Html\Text;
use ipl\I18n\Translation;
use ipl\Web\Url;
use ipl\Web\Widget\Link;
use Ramsey\Uuid\Uuid;

/**
 * A custom variable fields table
 */
class CustomVarFieldsTable extends Table
{
    use Translation;

    protected $defaultAttributes = [
        'class' => 'common-table table-row-selectable custom-var-fields-table',
        'data-base-target' => '_next',
    ];

    /**
     * @param array $properties    Rows to render, one per property
     * @param bool  $isFieldsTable Marks a nested fields table instead of the top level list,
     *                             currently has no effect inside this class
     */
    public function __construct(
        protected array $properties,
        protected bool $isFieldsTable = false
    ) {
    }

    protected function assemble(): void
    {
        foreach ($this->properties as $property) {
            $propertyUuid = DbUtil::binaryResult($property->uuid);
            $url = Url::fromPath(
                'director/customvar',
                ['uuid' => Uuid::fromBytes($propertyUuid)->toString()]
            );

            if (isset($property->parent_uuid)) {
                $parentUuid = DbUtil::binaryResult($property->parent_uuid);
                $url->addParams(['parent_uuid' => Uuid::fromBytes($parentUuid)->toString()]);
            }

            $columns = [
                static::td([HtmlElement::create('strong', null, new Link($property->key_name, $url))])
                      ->setSeparator(' '),
                static::td([Text::create($property->label)])->setSeparator(' '),
                static::td([Text::create($property->value_type)]),
            ];

            if (isset($property->used_count) && $property->used_count > 0) {
                $columns[] = static::td([Text::create($this->translate('In use'))]);
            } else {
                $columns[] = static::td([Text::create($this->translate('Not in use'))]);
            }

            $this->addHtml(static::tr($columns));
        }
    }
}
