<?php

namespace Icinga\Module\Director\Web\Hook;

use Icinga\Module\Director\Web\Form\QuickForm;

abstract class DataTypeHook
{
    public function getName()
    {
        $parts = explode('\\', get_class($this));
        $class = preg_replace('/DataType/', '', array_pop($parts));

        if (array_shift($parts) === 'Icinga' && array_shift($parts) === 'Module') {
            $module = array_shift($parts);
            if ($module !== 'Director') {
                return sprintf('%s (%s)', $class, $module);
            }
        }

        return $class;
    }

    public static function getFormat()
    {
        return 'string';
    }

    abstract public function getFormElement(QuickForm $form);
}
