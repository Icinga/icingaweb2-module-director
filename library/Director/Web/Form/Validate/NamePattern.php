<?php

namespace Icinga\Module\Director\Web\Form\Validate;

use Icinga\Data\Filter\FilterMatch;
use Icinga\Data\Filter\FilterOr;
use Zend_Validate_Abstract;

class NamePattern extends Zend_Validate_Abstract
{
    const INVALID = 'intInvalid';

    private $pattern;

    private $filter;

    public function __construct($pattern)
    {
        if (is_array($pattern) && count($pattern) === 1) {
            $this->pattern = current($pattern);
        } else {
            $this->pattern = $pattern;
        }

        if (is_array($this->pattern)) {
            $msg = implode(' | ', $this->pattern);
        } else {
            $msg = $this->pattern;
        }

        $this->_messageTemplates[self::INVALID] = sprintf(
            'Does not match %s',
            $msg
        );
    }

    protected function matches($value)
    {
        if ($this->filter === null) {
            if (is_array($this->pattern)) {
                $this->filter = new FilterOr();
                foreach ($this->pattern as $pattern) {
                    $filter = new FilterMatch('prop', '=', $pattern);
                    $filter->setCaseSensitive(false);
                    $this->filter->addFilter($filter);
                }
            } else {
                $this->filter = new FilterMatch('prop', '=', $this->pattern);
                $this->filter->setCaseSensitive(false);
            }
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
