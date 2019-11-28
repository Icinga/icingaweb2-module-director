<?php

namespace Icinga\Module\Director\CheckPlugin;

use Exception;

class Check extends CheckResults
{
    public function call(callable $check, $errorState = 'CRITICAL')
    {
        try {
            $check();
        } catch (Exception $e) {
            $this->fail($e, $errorState);
        }

        return $this;
    }

    public function assertTrue($check, $message, $errorState = 'CRITICAL')
    {
        if ($this->makeBool($check, $message) === true) {
            $this->succeed($message);
        } else {
            $this->fail($message, $errorState);
        }

        return $this;
    }

    public function assertFalse($check, $message, $errorState = 'CRITICAL')
    {
        if ($this->makeBool($check, $message) === false) {
            $this->succeed($message);
        } else {
            $this->fail($message, $errorState);
        }

        return $this;
    }

    protected function makeBool($check, &$message)
    {
        if (is_callable($check)) {
            try {
                $check = $check();
            } catch (Exception $e) {
                $message .= ': ' . $e->getMessage();
                return false;
            }
        }

        if (! is_bool($check)) {
            return null;
        }

        return $check;
    }
}
