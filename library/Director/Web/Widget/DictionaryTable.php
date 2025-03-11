<?php

namespace Icinga\Module\Director\Web\Widget;

use ipl\Html\Html;
use ipl\Html\Table;
use ipl\Web\Url;
use ipl\Web\Widget\Link;

class DictionaryTable extends Table
{
    protected $dictionaries = [];

    protected $showHeader;

    protected $defaultAttributes = [
        'class' => 'common-table table-row-selectable dictionary-table',
        'data-base-target' => '_next',
    ];

    public function __construct(array $dictionaries, bool $showHeader = true)
    {
        $this->dictionaries = $dictionaries;
        $this->showHeader = $showHeader;
    }

    protected function assemble()
    {

        foreach ($this->dictionaries as $dictionary) {
            $this->add(static::tr([
                static::td([
                    Html::tag('strong')->add(
                        new Link($dictionary->name, Url::fromPath('director/dictionary', ['uuid' => $dictionary->uuid]))
                    )
                ])->setSeparator(' '),
                static::td([Html::tag('p')->add($dictionary->label)])
            ]));
        }
    }
}