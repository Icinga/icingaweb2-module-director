<?php

namespace dipl\Web\Widget;

use dipl\Html\Table;

class NameValueTable extends Table
{
    protected $defaultAttributes = ['class' => 'name-value-table'];

    public function createNameValueRow($name, $value)
    {
        return $this::tr([$this::th($name), $this::td($value)]);
    }

    public function addNameValueRow($name, $value)
    {
        $this->body()->add($this->createNameValueRow($name, $value));
        return $this;
    }

    public function addNameValuePairs($pairs)
    {
        foreach ($pairs as $name => $value) {
            $this->addNameValueRow($name, $value);
        }

        return $this;
    }
}
