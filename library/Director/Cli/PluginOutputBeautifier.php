<?php

namespace Icinga\Module\Director\Cli;

use Icinga\Cli\Screen;

class PluginOutputBeautifier
{
    /** @var Screen */
    protected $screen;

    protected $isTty;

    protected $colorized;

    public function __construct(Screen $screen)
    {
        $this->screen = $screen;
    }

    public static function beautify($string, Screen $screen)
    {
        $self = new static($screen);
        if ($self->isTty()) {
            return $self->colorizeStates($string);
        } else {
            return $string;
        }
    }

    protected function colorizeStates($string)
    {
        $string = preg_replace_callback(
            "/'([^']+)'/",
            [$this, 'highlightNames'],
            $string
        );

        $string = preg_replace_callback(
            '/(OK|WARNING|CRITICAL|UNKNOWN)/',
            [$this, 'getColorized'],
            $string
        );

        return $string;
    }

    protected function isTty()
    {
        if ($this->isTty === null) {
            $this->isTty = function_exists('posix_isatty') && posix_isatty(STDOUT);
        }

        return $this->isTty;
    }

    protected function highlightNames($match)
    {
        return "'" . $this->screen->colorize($match[1], 'darkgray') . "'";
    }

    protected function getColorized($match)
    {
        if ($this->colorized === null) {
            $this->colorized = [
                'OK'       => $this->screen->colorize('OK', 'lightgreen'),
                'WARNING'  => $this->screen->colorize('WARNING', 'yellow'),
                'CRITICAL' => $this->screen->colorize('CRITICAL', 'lightred'),
                'UNKNOWN'  => $this->screen->colorize('UNKNOWN', 'lightpurple'),
            ];
        }

        return $this->colorized[$match[1]];
    }
}
