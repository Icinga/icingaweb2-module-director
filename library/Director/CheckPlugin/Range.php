<?php

namespace Icinga\Module\Director\CheckPlugin;

use Icinga\Exception\ConfigurationError;

class Range
{
    /** @var float|null */
    protected $start = 0;

    /** @var float|null */
    protected $end = null;

    /** @var bool */
    protected $mustBeWithinRange = true;

    public function __construct($start = 0, $end = null, $mustBeWithinRange = true)
    {
        $this->start = $start;
        $this->end = $end;
        $this->mustBeWithinRange = $mustBeWithinRange;
    }

    public function valueIsValid($value)
    {
        if ($this->valueIsWithinRange($value)) {
            return $this->valueMustBeWithinRange();
        } else {
            return ! $this->valueMustBeWithinRange();
        }
    }

    public function valueIsWithinRange($value)
    {
        if ($this->start !== null && $value < $this->start) {
            return false;
        }
        if ($this->end !== null && $value > $this->end) {
            return false;
        }

        return true;
    }

    public function valueMustBeWithinRange()
    {
        return $this->mustBeWithinRange;
    }

    /**
     * @param $any
     * @return static
     */
    public static function wantRange($any)
    {
        if ($any instanceof static) {
            return $any;
        } else {
            return static::parse($any);
        }
    }

    /**
     * @param $string
     * @return static
     * @throws ConfigurationError
     */
    public static function parse($string)
    {
        $string = str_replace(' ', '', $string);
        $value = '[-+]?[\d\.]+';
        $valueRe = "$value(?:e$value)?";
        $regex = "/^(@)?($valueRe|~)(:$valueRe|~)?/";
        if (! preg_match($regex, $string, $match)) {
            throw new ConfigurationError('Invalid range definition: %s', $string);
        }

        $inside = $match[1] === '@';

        if (strlen($match[3]) === 0) {
            $start = 0;
            $end = static::parseValue($match[2]);
        } else {
            $start = static::parseValue($match[2]);
            $end = static::parseValue($match[3]);
        }
        $range = new static($start, $end, $inside);

        return $range;
    }

    protected static function parseValue($value)
    {
        if ($value === '~') {
            return null;
        } else {
            return $value;
        }
    }
}
