<?php

namespace Icinga\Module\Director\IcingaConfig;

class HostStateFilterSet extends ExtensibleSet
{
    protected $allowedValues
        = array(
            'Up',
            'Down',
        );

    public function enumAllowedValues()
    {
        return array(
            $this->translate('Hosts') => array(
                'Up'   => $this->translate('Up'),
                'Down' => $this->translate('Down'),
            ),
        );
    }
}
