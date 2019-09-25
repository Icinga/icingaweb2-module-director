<?php

namespace Icinga\Module\Director\Daemon;

use gipfl\Cli\Process;
use gipfl\SystemD\NotifySystemD;

class DaemonProcessState
{
    /** @var NotifySystemD|null */
    protected $systemd;

    protected $components = [];

    protected $currentMessage;

    protected $processTitle;

    protected $state;

    public function __construct($processTitle)
    {
        $this->processTitle = $processTitle;
        $this->refreshMessage();
    }

    /**
     * @param NotifySystemD|false $systemd
     * @return $this
     */
    public function setSystemd($systemd)
    {
        if ($systemd) {
            $this->systemd = $systemd;
        } else {
            $this->systemd = null;
        }

        return $this;
    }

    public function setState($message)
    {
        $this->state = $message;
        $this->refreshMessage();

        return $this;
    }

    public function setComponentState($name, $stateMessage)
    {
        if ($stateMessage === null) {
            unset($this->components[$name]);
        } else {
            $this->components[$name] = $stateMessage;
        }
        $this->refreshMessage();
    }

    protected function refreshMessage()
    {
        $messageParts = [];
        if (\strlen($this->state)) {
            $messageParts[] = $this->state;
        }
        foreach ($this->components as $component => $message) {
            $messageParts[] = "$component: $message";
        }

        $message = \implode(', ', $messageParts);

        if ($message !== $this->currentMessage) {
            $this->currentMessage = $message;
            if (\strlen($message) === 0) {
                Process::setTitle($this->processTitle);
            } else {
                Process::setTitle($this->processTitle . ": $message");
            }

            if ($this->systemd) {
                $this->systemd->setStatus($message);
            }
        }
    }
}
