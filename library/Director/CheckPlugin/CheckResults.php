<?php

namespace Icinga\Module\Director\CheckPlugin;

use Exception;

class CheckResults
{
    /** @var string */
    protected $name;

    /** @var PluginState */
    protected $state;

    /** @var CheckResult[] */
    protected $results = [];

    protected $stateCounters = [
        0 => 0,
        1 => 0,
        2 => 0,
        3 => 0,
    ];

    public function __construct($name)
    {
        $this->name = $name;
        $this->state = new PluginState(0);
    }

    public function getName()
    {
        return $this->name;
    }

    public function add(CheckResult $result)
    {
        $this->results[] = $result;
        $this->state->raise($result->getState());
        $this->stateCounters[$result->getState()->getNumeric()]++;

        return $this;
    }

    public function getStateCounters()
    {
        return $this->stateCounters;
    }

    public function getProblemSummary()
    {
        $summary = [];
        for ($i = 1; $i <= 3; $i++) {
            $count = $this->stateCounters[$i];
            if ($count === 0) {
                continue;
            }
            $summary[PluginState::create($i)->getName()] = $count;
        }

        return $summary;
    }

    public function getStateSummaryString()
    {
        $summary = [sprintf(
            '%d tests OK',
            $this->stateCounters[0]
        )];

        for ($i = 1; $i <= 3; $i++) {
            $count = $this->stateCounters[$i];
            if ($count === 0) {
                continue;
            }
            $summary[] = sprintf(
                '%dx %s',
                $count,
                PluginState::create($i)->getName()
            );
        }

        return implode(', ', $summary);
    }

    public function getOutput()
    {
        $output = sprintf(
            "%s: %s\n",
            $this->name,
            $this->getStateSummaryString()
        );

        foreach ($this->results as $result) {
            $output .= sprintf(
                "[%s] %s\n",
                $result->getState()->getName(),
                $result->getOutput()
            );
        }

        return $output;
    }

    public function getResults()
    {
        return $this->results;
    }

    public function getState()
    {
        return $this->state;
    }

    public function hasProblems()
    {
        return $this->getState()->getNumeric() !== 0;
    }

    public function hasErrors()
    {
        $state = $this->getState()->getNumeric();
        return $state !== 0 && $state !== 1;
    }

    public function succeed($message)
    {
        $this->add(new CheckResult($message));

        return $this;
    }

    public function warn($message)
    {
        $this->add(new CheckResult($message, 1));

        return $this;
    }

    public function fail($message, $errorState = 'CRITICAL')
    {
        if ($message instanceof Exception) {
            $message = $message->getMessage();
        }

        $this->add(new CheckResult($message, $errorState));

        return $this;
    }
}
