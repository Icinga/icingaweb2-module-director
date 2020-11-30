<?php

namespace Icinga\Module\Director;

use gipfl\Diff\HtmlRenderer\InlineDiff;
use gipfl\Diff\HtmlRenderer\SideBySideDiff;
use gipfl\Diff\PhpDiff;
use ipl\Html\ValidHtml;
use InvalidArgumentException;

/**
 * @deprecated will be removed with v1.9 - please use gipfl\Diff
 */
class ConfigDiff implements ValidHtml
{
    protected $renderClass;

    /** @var PhpDiff */
    protected $phpDiff;

    public function __construct($a, $b)
    {
        $this->phpDiff = new PhpDiff($a, $b);
    }

    public function render()
    {
        $class = $this->renderClass;
        return (new $class($this->phpDiff))->render();
    }

    public function setHtmlRenderer($name)
    {
        switch ($name) {
            case 'SideBySide':
                $this->renderClass = SideBySideDiff::class;
                break;
            case 'Inline':
                $this->renderClass = InlineDiff::class;
                break;
            default:
                throw new InvalidArgumentException("There is no known '$name' renderer");
        }

        return $this;
    }
}
