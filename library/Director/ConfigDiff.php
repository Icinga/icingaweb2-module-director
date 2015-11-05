<?php

namespace Icinga\Module\Director;

use Diff;
use Diff_Renderer_Html_Inline;
use Diff_Renderer_Html_SideBySide;
use Diff_Renderer_Text_Context;
use Diff_Renderer_Text_Unified;
use Icinga\Application\Benchmark;

class ConfigDiff
{
    protected $a;

    protected $b;

    protected $diff;
    protected $opcodes;

    protected function __construct($a, $b)
    {
        require_once dirname(__DIR__) . '/vendor/php-diff/lib/Diff.php';

        $this->a = explode("\n", (string) $a);
        $this->b = explode("\n", (string) $b);

        $options = array(
            // 'ignoreWhitespace' => true,
            // 'ignoreCase' => true,
        );
        $this->diff = new Diff($this->a, $this->b, $options);
    }

    public function renderHtml()
    {
        return $this->renderHtmlSideBySide();
    }

    public function renderHtmlSideBySide()
    {
        require_once dirname(__DIR__)  . '/vendor/php-diff/lib/Diff/Renderer/Html/SideBySide.php';
        $renderer = new Diff_Renderer_Html_SideBySide;
        return $this->diff->Render($renderer);
    }

    public function renderHtmlInline()
    {
        require_once dirname(__DIR__)  . '/vendor/php-diff/lib/Diff/Renderer/Html/Inline.php';
        $renderer = new Diff_Renderer_Html_Inline;
        return $this->diff->Render($renderer);
    }

    public function renderTextContext()
    {
        require_once dirname(__DIR__)  . '/vendor/php-diff/lib/Diff/Renderer/Text/Context.php';
        $renderer = new Diff_Renderer_Text_Context;
        return $this->diff->Render($renderer);
    }

    public function renderTextUnified()
    {
        require_once dirname(__DIR__)  . '/vendor/php-diff/lib/Diff/Renderer/Text/Context.php';
        $renderer = new Diff_Renderer_Text_Context;
        return $this->diff->Render($renderer);
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
