<?php

// SPDX-FileCopyrightText: 2018 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Icinga\Module\Director\IcingaConfig;

class StateFilterSet extends ExtensibleSet
{
    protected $allowedValues = array(
        'Up',
        'Down',
        'OK',
        'Warning',
        'Critical',
        'Unknown',
    );

    public function enumAllowedValues()
    {
        return array(
            $this->translate('Hosts') => array(
                'Up'       => $this->translate('Up'),
                'Down'     => $this->translate('Down')
            ),
            $this->translate('Services') => array(
                'OK'       => $this->translate('OK'),
                'Warning'  => $this->translate('Warning'),
                'Critical' => $this->translate('Critical'),
                'Unknown'  => $this->translate('Unknown'),
            ),
        );
    }
}
