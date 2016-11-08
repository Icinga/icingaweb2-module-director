<?php

namespace Icinga\Module\Director;

/**
 * PSR-4 ClassLoader for namespace extensions inside Icinga Director
 *
 * e.g. \Icinga\Module\Director\Forms\SomeTestForm -> ./application/forms/SomeTestForm.php
 *
 * @package Icinga\Module\Director
 */
class ClassLoader
{
    /**
     * Prefix to support here
     */
    const NAMESPACE_PREFIX = 'Icinga\\Module\\Director\\';

    /**
     * Hard-coded length of NAMESPACE_PREFIX
     */
    const PREFIX_LENGTH = 23;

    /**
     * Namespaces below ./application that get loaded
     * @var array
     */
    protected static $APPLICATION_NAMESPACES = array('tables');

    /**
     * Absolute path of the installed module
     *
     * @var string
     */
    protected $moduleHome;

    public function __construct($moduleHome)
    {
        $this->moduleHome = $moduleHome;
    }

    /**
     * Loader function for spl_autoload
     *
     * @param $class
     */
    public function loadClass($class)
    {
        if (strncmp(self::NAMESPACE_PREFIX, $class, self::PREFIX_LENGTH) !== 0) {
            return;
        }

        // get the relative class name
        $relativeClass = substr($class, self::PREFIX_LENGTH);
        $relativeClassA = explode('\\', $relativeClass);

        if (! empty($relativeClassA)) {
            $subPath = strtolower(array_shift($relativeClassA));

            if (in_array($subPath, self::$APPLICATION_NAMESPACES)) {
                $file = $this->moduleHome
                    . DIRECTORY_SEPARATOR
                    . 'application'
                    . DIRECTORY_SEPARATOR
                    . $subPath
                    . DIRECTORY_SEPARATOR
                    . implode(DIRECTORY_SEPARATOR, $relativeClassA)
                    . '.php';

                if (file_exists($file)) {
                    require $file;
                }
            }
        }
    }

    /**
     * Register {@link loadClass()} as an autoloader
     */
    public function register()
    {
        spl_autoload_register(array($this, 'loadClass'));
    }

    /**
     * Unregister {@link loadClass()} as an autoloader
     */
    public function unregister()
    {
        spl_autoload_unregister(array($this, 'loadClass'));
    }

    /**
     * Unregister this as an autoloader
     */
    public function __destruct()
    {
        $this->unregister();
    }
}
