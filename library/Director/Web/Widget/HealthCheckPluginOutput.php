<?php

namespace Icinga\Module\Director\Web\Widget;

use ipl\Html\Html;
use ipl\Html\HtmlDocument;
use ipl\Html\HtmlString;
use gipfl\Translation\TranslationHelper;
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
                $title = Html::tag('h1', $check->getName()),
                $ul = Html::tag('ul', ['class' => 'health-check-result'])
            ]);

            $problems = $check->getProblemSummary();
            if (! empty($problems)) {
                $badges = Html::tag('span', ['class' => 'title-badges']);
                foreach ($problems as $state => $count) {
                    $badges->add(Html::tag('span', [
                        'class' => ['badge', 'state-' . strtolower($state)],
                        'title' => sprintf(
                            $this->translate('%s: %d'),
                            $this->translate($state),
                            $count
                        ),
                    ], $count));
                }
                $title->add($badges);
            }

            foreach ($check->getResults() as $result) {
                $state = $result->getState()->getName();
                $ul->add(Html::tag('li', [
                    'class' => 'state state-' . strtolower($state)
                ], $this->highlightNames($result->getOutput()))->setSeparator(' '));
            }
            $this->state->raise($check->getState());
        }
    }

    public function getState()
    {
        return $this->state;
    }

    protected function colorizeState($state)
    {
        return Html::tag('span', ['class' => 'badge state-' . strtolower($state)], $state);
    }

    protected function highlightNames($string)
    {
        $string = Html::escape($string);
        return new HtmlString(preg_replace_callback(
            "/'([^']+)'/",
            [$this, 'highlightName'],
            $string
        ));
    }

    protected function highlightName($match)
    {
        return '"' . Html::tag('strong', $match[1]) . '"';
    }

    protected function getColorized($match)
    {
        return $this->colorizeState($match[1]);
    }
}
