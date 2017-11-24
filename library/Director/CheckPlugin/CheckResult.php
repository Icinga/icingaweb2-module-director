<?php

namespace Icinga\Module\Director\CheckPlugin;

class CheckResult
{
    protected $state;

    protected $output;

    public function __construct($output, $state = 0)
    {
        if ($state instanceof PluginState) {
            $this->state = $state;
        } else {
            $this->state = new PluginState($state);
        }

        $this->output = $output;
    }

    public function getState()
    {
        return $this->state;
    }

    public function getOutput()
    {
        return $this->output;
    }
}
