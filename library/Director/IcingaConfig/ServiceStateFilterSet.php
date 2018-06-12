<?php

namespace Icinga\Module\Director\IcingaConfig;

class ServiceStateFilterSet extends ExtensibleSet
{
    protected $allowedValues
        = array(
            'OK',
            'Warning',
            'Critical',
            'Unknown',
        );

    public function enumAllowedValues()
    {
        return array(
            $this->translate('Services') => array(
                'OK'       => $this->translate('OK'),
                'Warning'  => $this->translate('Warning'),
                'Critical' => $this->translate('Critical'),
                'Unknown'  => $this->translate('Unknown'),
            ),
        );
    }
}

