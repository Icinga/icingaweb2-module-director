<?php

namespace Icinga\Module\Director\Test;

abstract class TestSuiteUnit
{
    public function run()
    {
    }
    public function __construct()
    {
        $this->testdoxFile = $this->newTempfile();
    }

    public function __destruct()
    {
        if ($this->testdoxFile && file_exists($this->testdoxFile)) {
            unlink($this->testDoxfile);
        }
    }

    public function getPhpunitCommand()
    {
        // return phpunit --bootstrap test/bootstrap.php  --testdox-text /tmp/testdox.txt .
    }
}
