<?php

namespace Icinga\Module\Director\Web\Widget;

use ipl\Html\HtmlDocument;
use Icinga\Module\Director\IcingaConfig\IcingaConfigFile;
use ipl\Html\Html;
use ipl\Html\HtmlString;
use gipfl\IcingaWeb2\Link;
use gipfl\Translation\TranslationHelper;

class ShowConfigFile extends HtmlDocument
{
    use TranslationHelper;

    protected $file;

    protected $highlight;

    protected $highlightSeverity;

    public function __construct(
        IcingaConfigFile $file,
        $highlight = null,
        $highlightSeverity = null
    ) {
        $this->file = $file;
        $this->highlight         = $highlight;
        $this->highlightSeverity = $highlightSeverity;
    }

    /**
     * @throws \Icinga\Exception\IcingaException
     */
    protected function assemble()
    {
        $source = $this->linkObjects(Html::escape($this->file->getContent()));
        if ($this->highlight) {
            $source = $this->highlight(
                $source,
                $this->highlight,
                $this->highlightSeverity
            );
        }

        $this->add(Html::tag(
            'pre',
            ['class' => 'generated-config'],
            new HtmlString($source)
        ));
    }

    /**
     * @param $match
     * @return string
     * @throws \Icinga\Exception\IcingaException
     * @throws \Icinga\Exception\ProgrammingError
     */
    protected function linkObject($match)
    {
        if ($match[2] === 'Service') {
            return $match[0];
        }
        $controller = $match[2];

        if ($match[2] === 'CheckCommand') {
            $controller = 'command';
        }

        $name = $this->decode($match[3]);
        return sprintf(
            '%s %s &quot;%s&quot; {',
            $match[1],
            $match[2],
            Link::create(
                $name,
                'director/' . $controller,
                ['name' => $name],
                ['data-base-target' => '_next']
            )
        );
    }

    protected function decode($str)
    {
        return htmlspecialchars_decode($str, ENT_COMPAT | ENT_SUBSTITUTE | ENT_HTML5);
    }

    protected function linkObjects($config)
    {
        $pattern = '/^(object|template)\s([A-Z][A-Za-z]*?)\s&quot;(.+?)&quot;\s{/m';

        return preg_replace_callback(
            $pattern,
            [$this, 'linkObject'],
            $config
        );
    }

    protected function highlight($what, $line, $severity)
    {
        $lines = explode("\n", $what);
        $lines[$line - 1] = '<span class="highlight ' . $severity . '">' . $lines[$line - 1] . '</span>';
        return implode("\n", $lines);
    }
}
