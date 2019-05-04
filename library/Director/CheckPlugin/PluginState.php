<?php

namespace Icinga\Module\Director\CheckPlugin;

use Icinga\Exception\ProgrammingError;

class PluginState
{
    protected static $stateCodes = [
        'UNKNOWN'  => 3,
        'CRITICAL' => 2,
        'WARNING'  => 1,
        'OK'       => 0,
    ];

    protected static $stateNames = [
        'OK',
        'WARNING',
        'CRITICAL',
        'UNKNOWN',
    ];

    protected static $sortSeverity = [0, 1, 3, 2];

    /** @var int */
    protected $state;

    public function __construct($state)
    {
        $this->set($state);
    }

    public function isProblem()
    {
        return $this->state > 0;
    }

    public function set($state)
    {
        $this->state = $this->getNumericStateFor($state);
    }

    public function getNumeric()
    {
        return $this->state;
    }

    public function getSortSeverity()
    {
        return static::getSortSeverityFor($this->getNumeric());
    }

    public function getName()
    {
        return self::$stateNames[$this->getNumeric()];
    }

    public function raise(PluginState $state)
    {
        if ($this->getSortSeverity() < $state->getSortSeverity()) {
            $this->state = $state->getNumeric();
        }

        return $this;
    }

    public static function create($state)
    {
        return new static($state);
    }

    public static function ok()
    {
        return new static(0);
    }

    public static function warning()
    {
        return new static(1);
    }

    public static function critical()
    {
        return new static(2);
    }

    public static function unknown()
    {
        return new static(3);
    }

    protected static function getNumericStateFor($state)
    {
        if ((is_int($state) || ctype_digit($state)) && $state >= 0 && $state <= 3) {
            return (int) $state;
        } elseif (is_string($state) && array_key_exists($state, self::$stateCodes)) {
            return self::$stateCodes[$state];
        } else {
            throw new ProgrammingError('Expected valid state, got: %s', $state);
        }
    }

    protected static function getSortSeverityFor($state)
    {
        if (array_key_exists($state, self::$sortSeverity)) {
            return self::$sortSeverity[$state];
        } else {
            throw new ProgrammingError(
                'Unable to retrieve sort severity for invalid state: %s',
                $state
            );
        }
    }
}
