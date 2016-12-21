<?php

namespace Icinga\Module\Director\Test;

use Closure;

class TestProcess
{
    protected $command;

    protected $identifier;

    protected $exitCode;

    protected $output;

    protected $onSuccess;

    protected $onFailure;

    protected $expectedExitCode = 0;

    public function __construct($command, $identifier = null)
    {
        $this->command    = $command;
        $this->identifier = $identifier;
    }

    public function getIdentifier()
    {
        return $this->identifier;
    }

    public function expectExitCode($code)
    {
        $this->expectedExitCode = $code;
        return $this;
    }

    public function onSuccess($func)
    {
        $this->onSuccess = $this->makeClosure($func);
        return $this;
    }

    public function onFailure($func)
    {
        $this->onSuccess = $this->makeClosure($func);
        return $this;
    }

    protected function makeClosure($func)
    {
        if ($func instanceof Closure) {
            return $func;
        }

        if (is_array($func)) {
            return function ($process) use ($func) {
                return $func[0]->{$func[1]}($process);
            };
        }
    }

    public function onFailureThrow($message, $class = 'Exception')
    {
        return $this->onFailure(function () use ($message, $class) {
            throw new $class($message);
        });
    }

    public function run()
    {
        exec($this->command, $this->output, $this->exitCode);

        if ($this->succeeded()) {
            $this->triggerSuccess();
        } else {
            $this->triggerFailure();
        }
    }

    public function succeeded()
    {
        return $this->exitCode === $this->expectedExitCode;
    }

    public function failed()
    {
        return $this->exitCode !== $this->expectedExitCode;
    }

    protected function triggerSuccess()
    {
        if (($func = $this->onSuccess) !== null) {
            $func($this);
        }
    }

    protected function triggerFailure()
    {
        if (($func = $this->onFailure) !== null) {
            $func($this);
        }
    }

    public function getExitCode()
    {
        return $this->exitCode;
    }

    public function getOutput()
    {
        return implode("\n", $this->output) . "\n";
    }
}
