<?php

namespace Icinga\Module\Director\Web\Table;

use Icinga\Application\Icinga;
use Icinga\Application\Modules\Module;
use Icinga\Exception\ProgrammingError;

class TableLoader
{
    /** @return QuickTable */
    public static function load($name, ?Module $module = null)
    {
        if ($module === null) {
            $basedir = Icinga::app()->getApplicationDir('tables');
            $ns = '\\Icinga\\Web\\Tables\\';
        } else {
            $basedir = $module->getBaseDir() . '/application/tables';
            $ns = '\\Icinga\\Module\\' . ucfirst($module->getName()) . '\\Tables\\';
        }
        if (preg_match('~^[a-z0-9/]+$~i', $name)) {
            $parts = preg_split('~/~', $name);
            $class = ucfirst(array_pop($parts)) . 'Table';
            $file = sprintf('%s/%s/%s.php', rtrim($basedir, '/'), implode('/', $parts), $class);
            if (file_exists($file)) {
                require_once($file);
                /** @var QuickTable $class */
                $class = $ns . $class;
                return new $class();
            }
        }
        throw new ProgrammingError(sprintf('Cannot load %s (%s), no such table', $name, $file));
    }
}
