<?php

namespace Icinga\Module\Director;

use FineDiff;
use Icinga\Application\Benchmark;

class ConfigDiff
{
    protected $a;

    protected $b;

    protected $diff;
    protected $opcodes;

    protected function __construct($a, $b)
    {
        $this->a = $a;
        $this->b = $b;
        require_once dirname(__DIR__) . '/vendor/PHP-FineDiff/finediff.php';

        // Trying character granularity first...
        $granularity = FineDiff::$characterGranularity;
        $this->diff = new FineDiff($a, $b, $granularity);
        if (count($this->diff->getOps()) > 4) {
            // ...fall back to word granularity if too many differences
            // (available granularities: character, word, sentence, paragraph
            $granularity = FineDiff::$wordGranularity;
            $this->diff = new FineDiff($a, $b, $granularity);
        }
    }

    public function renderHtml()
    {
        return $this->diff->renderDiffToHTML();
    }

    public function __toString()
    {
        return $this->renderHtml();
    }

    public static function create($a, $b)
    {
        $diff = new static($a, $b);
        return $diff;
    }
}
