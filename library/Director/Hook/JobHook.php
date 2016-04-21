<?php

namespace Icinga\Module\Director\Hook;

abstract class JobHook
{
    protected $settings = array();

    public function getName()
    {
        $parts = explode('\\', get_class($this));
        $class = preg_replace('/Job$/', '', array_pop($parts));

        if (array_shift($parts) === 'Icinga' && array_shift($parts) === 'Module') {
            $module = array_shift($parts);
            if ($module !== 'Director') {
                return sprintf('%s (%s)', $class, $module);
            }
        }

        return $class;
    }

    abstract public run();

    abstract public function isPending();
}
