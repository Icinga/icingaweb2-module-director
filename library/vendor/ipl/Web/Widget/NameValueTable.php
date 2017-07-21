<?php

namespace ipl\Web\Widget;

use ipl\Html\Table;

class NameValueTable extends Table
{
    protected $defaultAttributes = ['class' => 'name-value-table'];

    public function createNameValueRow($name, $value)
    {
        return $this::tr([$this::th($name), $this::td($value)]);
    }

    public function addNameValueRow($name, $value)
    {
        return $this->body()->add($this->createNameValueRow($name, $value));
    }

    public function addNameValuePairs($pairs)
    {
        foreach ($pairs as $name => $value) {
            $this->addNameValueRow($name, $value);
        }

        return $this;
    }
}
