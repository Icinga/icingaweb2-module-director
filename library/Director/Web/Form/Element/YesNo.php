<?php

namespace Icinga\Module\Director\Web\Form\Element;

/**
 * Input control for booleans, gives y/n
 */
class YesNo extends OptionalYesNo
{
    public $options = array(
        'y'  => 'Yes',
        'n'  => 'No',
    );
}
