<?php

namespace Icinga\Module\Director\Web\Form\Validate;

use Icinga\Module\Director\Restriction\MatchingFilter;
use Zend_Validate_Abstract;

class NamePattern extends Zend_Validate_Abstract
{
    public const INVALID = 'intInvalid';

    private $filter;

    public function __construct($pattern)
    {
        if (! is_array($pattern)) {
            $pattern = [$pattern];
        }

        $this->filter = MatchingFilter::forPatterns($pattern, 'value');

        $this->_messageTemplates[self::INVALID] = sprintf(
            'Does not match %s',
            (string) $this->filter
        );
    }

    public function isValid($value)
    {
        if ($this->filter->matches((object) ['value' => $value])) {
            return true;
        } else {
            $this->_error(self::INVALID, $value);

            return false;
        }
    }
}
