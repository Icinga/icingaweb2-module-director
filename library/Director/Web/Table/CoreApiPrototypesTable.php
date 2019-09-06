<?php

namespace Icinga\Module\Director\Web\Table;

use dipl\Html\Table;
use dipl\Translation\TranslationHelper;

class CoreApiPrototypesTable extends Table
{
    use TranslationHelper;

    protected $defaultAttributes = ['class' => ['common-table']];

    protected $prototypes;

    protected $typeName;

    public function __construct($prototypes, $typeName)
    {
        $this->prototypes = $prototypes;
        $this->typeName = $typeName;
    }

    public function assemble()
    {
        $this->header();
        $body = $this->body();
        $type = $this->typeName;
        foreach ($this->prototypes as $name) {
            $body->add($this::tr($this::td("$type.$name()")));
        }
    }

    public function getColumnsToBeRendered()
    {
        return [
            $this->translate('Name'),
        ];
    }
}
