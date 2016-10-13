<?php

namespace Icinga\Module\Director\Test;

use Icinga\Application\Icinga;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

abstract class TestSuite
{
    private $basedir;

    abstract public function run();

    public static function newTempfile()
    {
        return tempnam(sys_get_temp_dir(), 'DirectorTest-');
    }

    public function process($command, $identifier = null)
    {
        return new TestProcess($command, $identifier);
    }

    protected function filesByExtension($base, $extensions)
    {
        $files = array();

        if (! is_array($extensions)) {
            $extensions = array($extensions);
        }

        $basedir = $this->getBaseDir() . '/' . $base;
        $dir = new RecursiveDirectoryIterator($basedir);
        $iterator = new RecursiveIteratorIterator(
            $dir,
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $file) {
            if (! $file->isFile()) {
                continue;
            }

            if (in_array($file->getExtension(), $extensions)) {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }

    public function getBaseDir($file = null)
    {
        if ($this->basedir === null) {
            $this->basedir = Icinga::app()
                ->getModuleManager()
                ->getModule('director')
                ->getBaseDir();
        }

        if ($file === null) {
            return $this->basedir;
        } else {
            return $this->basedir . '/' . $file;
        }
    }
}
