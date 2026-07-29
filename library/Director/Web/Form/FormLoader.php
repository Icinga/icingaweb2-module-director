<?php

namespace Icinga\Module\Director\Web\Form;

use Icinga\Application\Icinga;
use Icinga\Application\Modules\Module;
use Icinga\Exception\ProgrammingError;
use RuntimeException;

class FormLoader
{
    public static function load($name, ?Module $module = null)
    {
        if ($module === null) {
            try {
                $basedir = Icinga::app()->getApplicationDir('forms');
            } catch (ProgrammingError $e) {
                throw new RuntimeException($e->getMessage(), 0, $e);
            }
            $ns = '\\Icinga\\Web\\Forms\\';
        } else {
            $basedir = $module->getFormDir();
            $ns = '\\Icinga\\Module\\' . ucfirst($module->getName()) . '\\Forms\\';
        }
        if (preg_match('~^[a-z0-9/]+$~i', $name)) {
            $parts = preg_split('~/~', $name);
            $class = ucfirst(array_pop($parts)) . 'Form';
            $file = sprintf('%s/%s/%s.php', rtrim($basedir, '/'), implode('/', $parts), $class);
            if (file_exists($file)) {
                require_once($file);
                $class = $ns . $class;
                $options = array();
                if ($module !== null) {
                    $options['icingaModule'] = $module;
                }

                return new $class($options);
            }
        }

        throw new RuntimeException(sprintf('Cannot load %s (%s), no such form', $name, $file));
    }
}
