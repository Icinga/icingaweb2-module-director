<?php

namespace Icinga\Module\Director\Web\Table;

use Icinga\Module\Director\Objects\IcingaEndpoint;
use gipfl\IcingaWeb2\Link;
use ipl\Html\Html;
use ipl\Html\Table;
use gipfl\Translation\TranslationHelper;

class CoreApiObjectsTable extends Table
{
    use TranslationHelper;

    protected $defaultAttributes = [
        'class' => ['common-table', 'table-row-selectable'],
        'data-base-target' => '_next',
    ];

    /** @var IcingaEndpoint */
    protected $endpoint;

    protected $objects;

    protected $type;

    public function __construct($objects, IcingaEndpoint $endpoint, $type)
    {
        $this->objects = $objects;
        $this->endpoint = $endpoint;
        $this->type = $type;
    }

    public function assemble()
    {
        if (empty($this->objects)) {
            return;
        }
        $this->add(Html::tag('thead', Html::tag('tr', Html::wrapEach($this->getColumnsToBeRendered(), 'th'))));
        foreach ($this->objects as $name) {
            $this->add($this::tr($this::td(Link::create(
                str_replace('!', ': ', $name),
                'director/inspect/object',
                [
                    'name'     => $name,
                    'type'     => $this->type->name,
                    'plural'   => $this->type->plural_name,
                    'endpoint' => $this->endpoint->getObjectName()
                ]
            ))));
        }
    }

    public function getColumnsToBeRendered()
    {
        return [
            $this->translate('Name'),
        ];
    }
}
