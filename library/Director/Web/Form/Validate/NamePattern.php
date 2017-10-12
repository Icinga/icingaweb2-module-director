<?php

namespace Icinga\Module\Director\Web\Form\Validate;

use Icinga\Data\Filter\FilterMatch;
use Zend_Validate_Abstract;

class NamePattern extends Zend_Validate_Abstract
{
    const INVALID = 'intInvalid';

    private $pattern;

    private $filter;

    public function __construct($pattern)
    {
        $this->pattern = $pattern;
        $this->_messageTemplates[self::INVALID] = sprintf(
            'Does not match %s',
            $pattern
        );
    }

    protected function matches($value)
    {
        if ($this->filter === null) {
            $this->filter = new FilterMatch('prop', '=', $this->pattern);
            $this->filter->setCaseSensitive(false);
        }

        return $this->filter->matches($value);
    }

    public function isValid($value)
    {
        if ($this->matches((object) ['prop' => $value])) {
            return true;
        } else {
            $this->_error(self::INVALID, $value);

            return false;
        }
    }
}
