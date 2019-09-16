<?php

namespace Icinga\Module\Director\Web\Table;

use ipl\Html\Html;
use ipl\Html\Table;
use gipfl\Translation\TranslationHelper;

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
        if (empty($this->prototypes)) {
            return;
        }
        $this->add(Html::tag('thead', Html::tag('tr', Html::wrapEach($this->getColumnsToBeRendered(), 'th'))));
        $type = $this->typeName;
        foreach ($this->prototypes as $name) {
            $this->add($this::tr($this::td("$type.$name()")));
        }
    }

    public function getColumnsToBeRendered()
    {
        return [
            $this->translate('Name'),
        ];
    }
}
