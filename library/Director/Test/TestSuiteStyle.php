<?php

namespace Icinga\Module\Director\Test;

class TestSuiteStyle extends TestSuite
{
    public function run()
    {
        $out = static::newTempFile();
        $check = array(
            'library/Director/',
            'application/',
            'configuration.php',
            'run.php',
        );

        /*
        $options = array();
        if ($this->isVerbose) {
            $options[] = '-v';
        }
        */

        /*
        $phpcs = exec('which phpcs');
        if (!file_exists($phpcs)) {
            $this->fail(
                'PHP_CodeSniffer not found. Please install PHP_CodeSniffer to be able to run code style tests.'
            );
        }
        */

        $cmd = sprintf(
            "phpcs -p --standard=PSR2 --extensions=php --encoding=utf-8 -w -s --report-checkstyle=%s '%s'",
            $out,
            implode("' '", $check)
        );

        $proc = $this
            ->process($cmd);

        //  ->onFailure(array($this, 'failedCheck'))
        $proc->run();

        echo $proc->getOutput();

        echo file_get_contents($out);
        unlink($out);
        // /usr/bin/phpcs --standard=PSR2 --extensions=php --encoding=utf-8 application/
        //    library/Director/ --report=full

        /*
            $options[] = '--log-junit';
            $options[] = $reportPath . '/phpunit_results.xml';
            $options[] = '--coverage-html';
            $options[] = $reportPath . '/php_html_coverage';
        */
        return;

        `$cmd`;
        echo $cmd . "\n";
        echo $out ."\n";
        echo file_get_contents($out);
        unlink($out);

    }
}
