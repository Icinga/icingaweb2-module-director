<?php

namespace ipl\Web\Table\Extension;

use ipl\Web\Url;

// Could also be a static method, MultiSelect::enable($table)
trait MultiSelect
{
    protected function enableMultiSelect($url, $sourceUrl, array $keys)
    {
        $this->addAttributes([
            'class' => 'multiselect'
        ]);

        $prefix = 'data-icinga-multiselect';
        $multi = [
            "$prefix-url"         => Url::fromPath($url),
            "$prefix-controllers" => Url::fromPath($sourceUrl),
            "$prefix-data"        => implode(',', $keys),
        ];

        $this->addAttributes($multi);

        return $this;
    }
}
