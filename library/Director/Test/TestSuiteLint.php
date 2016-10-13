<?php

namespace Icinga\Module\Director\Test;

use Icinga\Application\Logger;

class TestSuiteLint extends TestSuite
{
    protected $checked;

    protected $failed;

    public function run()
    {
        $this->checked = $this->failed = array();

        foreach ($this->listFiles() as $file) {
            $checked[] = $file;
            $cmd = "php -l '$file'";
            $this->result[$file] = $this
                ->process($cmd, $file)
                ->onFailure(array($this, 'failedCheck'))
                ->run();
        }
    }

    public function failedCheck($process)
    {
        Logger::error($process->getOutput());
        $this->failed[] = $process->getIdentifier();
    }

    public function hasFailures()
    {
        return ! empty($this->failed);
    }

    protected function listFiles()
    {
        $basedir = $this->getBaseDir();
        $files = array(
            $basedir . '/run.php',
            $basedir . '/configuration.php'
        );

        foreach ($this->filesByExtension('library/Director', 'php') as $file) {
            $files[] = $file;
        }

        foreach ($this->filesByExtension('application', array('php', 'phtml')) as $file) {
            $files[] = $file;
        }

        return $files;
    }
}
