<?php

namespace Icinga\Module\Director\Web\Widget;

use dipl\Html\Html;
use dipl\Html\HtmlDocument;
use dipl\Html\HtmlString;
use dipl\Translation\TranslationHelper;
use Icinga\Module\Director\CheckPlugin\PluginState;
use Icinga\Module\Director\Health;

class HealthCheckPluginOutput extends HtmlDocument
{
    use TranslationHelper;

    /** @var Health */
    protected $health;

    /** @var PluginState */
    protected $state;

    public function __construct(Health $health)
    {
        $this->state = new PluginState('OK');
        $this->health = $health;
        $this->process();
    }

    protected function process()
    {
        $checks = $this->health->getAllChecks();

        foreach ($checks as $check) {
            $this->add([
                $title = Html::tag('h2', $check->getName()),
                $ul = Html::tag('ul', ['class' => 'health-check-result'])
            ]);

            $problems = $check->getProblemSummary();
            if (! empty($problems)) {
                $badges = Html::tag('span', ['class' => 'title-badges']);
                foreach ($problems as $state => $count) {
                    $badges->add(Html::tag('span', [
                        'class' => ['badge', 'state-' . strtolower($state)],
                        'title' => $this->translate('Critical Checks'),
                    ], $count));
                }
                $title->add($badges);
            }

            foreach ($check->getResults() as $result) {
                $ul->add(Html::tag('li', [
                    $this->colorizeState($result->getState()->getName()),
                    $this->colorizeStates($result->getOutput())
                ])->setSeparator(' '));
            }
            $this->state->raise($check->getState());
        }
    }

    public function getState()
    {
        return $this->state;
    }

    protected function colorizeStates($string)
    {
        $string = Html::escape($string);
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

        return new HtmlString($string);
    }

    protected function colorizeState($state)
    {
        return Html::tag('span', ['class' => 'badge state-' . strtolower($state)], $state);
    }

    protected function highlightNames($match)
    {
        return '"' . Html::tag('strong', $match[1]) . '"';
    }

    protected function getColorized($match)
    {
        return $this->colorizeState($match[1]);
    }
}
