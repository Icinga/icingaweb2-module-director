<?php

namespace Icinga\Module\Director\Hook;

use Icinga\Module\Director\Db;

abstract class JobHook
{
    private $db;

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

    public function setDb(Db $db)
    {
        $this->db = $db;
        return $this;
    }

    protected function db()
    {
        return $this->db;
    }

    abstract public run();

    abstract public function isPending();
}
