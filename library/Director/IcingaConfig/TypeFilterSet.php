<?php

namespace Icinga\Module\Director\IcingaConfig;

class TypeFilterSet extends ExtensibleSet
{
    protected $allowedValues = array(
        'Problem',
        'Recovery',
        'Custom',
        'Acknowledgement',
        'DowntimeStart',
        'DowntimeEnd',
        'DowntimeRemoved',
        'FlappingStart',
        'FlappingEnd',
    );

    public function enumAllowedValues()
    {
        return array(
            $this->translate('State changes') => array(
                'Problem'         => $this->translate('Problem'),
                'Recovery'        => $this->translate('Recovery'),
                'Custom'          => $this->translate('Custom notification'),
            ),
            $this->translate('Problem handling') => array(
                'Acknowledgement' => $this->translate('Acknowledgement'),
                'DowntimeStart'   => $this->translate('Downtime starts'),
                'DowntimeEnd'     => $this->translate('Downtime ends'),
                'DowntimeRemoved' => $this->translate('Downtime removed'),
            ),
            $this->translate('Flapping') => array(
                'FlappingStart'   => $this->translate('Flapping starts'),
                'FlappingEnd'     => $this->translate('Flapping ends')
            )
        );
    }
}
