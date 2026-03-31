<?php

namespace Icinga\Module\Director\Web\Form\Element;

use gipfl\Json\JsonString;
use Icinga\Data\Filter\Filter;
use Icinga\Data\Filter\FilterChain;
use Icinga\Data\Filter\FilterExpression;
use Icinga\Exception\ProgrammingError;
use Icinga\Module\Director\Web\Form\IconHelper;
use Exception;

/**
 * Input control for extensible sets
 */
class DataFilter extends FormElement
{
    /**
     * Default form view helper to use for rendering
     * @var string
     */
    public $helper = 'formDataFilter';

    private $addTo;

    private $removeFilter;

    private $stripFilter;

    /** @var FilterChain */
    private $filter;

    public function getValue()
    {
        $value = parent::getValue();
        if ($value !== null && $this->isEmpty($value)) {
            $value = null;
        }

        return $value;
    }

    protected function isEmpty(Filter $filter)
    {
        return $filter->isEmpty() || $this->isEmptyExpression($filter);
    }

    protected function isEmptyExpression(Filter $filter)
    {
        return $filter instanceof FilterExpression &&
            $filter->getColumn() === '' &&
            $filter->getExpression() === '""'; // -> json_encode('')
    }

    /**
     * @inheritdoc
     * @codingStandardsIgnoreStart
     */
    protected function _filterValue(&$value, &$key)
    {
        // @codingStandardsIgnoreEnd
        try {
            if ($value instanceof Filter) {
                // OK
            } elseif (is_string($value)) {
                $value = Filter::fromQueryString($value);
            } elseif (is_array($value) || is_null($value)) {
                $value = $this->arrayToFilter($value);
            } else {
                throw new ProgrammingError(
                    'Value to be filtered has to be Filter, string, array or null'
                );
            }
        } catch (Exception $e) {
            $value = null;
            // TODO: getFile, getLine
            // Hint: cannot addMessage at it would loop through getValue
            $this->addErrorMessage($e->getMessage());
            $this->_isErrorForced = true;
        }
    }

    /**
     * This method transforms filter form data into a filter
     * and reacts on pressed buttons
     *
     * @param  array|null $array
     *
     * @return FilterChain|null
     */
    protected function arrayToFilter($array)
    {
        if ($array === null) {
            return null;
        }

        $this->filter = null;
        foreach ($array as $id => $entry) {
            $filterId = $this->idToFilterId($id);
            $sub = $this->entryToFilter($entry)->setId($filterId);
            $this->checkEntryForActions($filterId, $entry);
            $parentId = $this->parentIdFor($filterId);

            if ($this->filter === null) {
                $this->filter = $sub;
            } else {
                $this->filter->getById($parentId)->addFilter($sub);
            }
        }

        $this->removeFilterIfRequested()
             ->stripFilterIfRequested()
             ->addNewFilterIfRequested()
             ->fixNotsWithMultipleChildren();

        return $this->filter;
    }

    protected function removeFilterIfRequested()
    {
        if ($this->removeFilter !== null) {
            if ($this->filter->getById($this->removeFilter)->isRootNode()) {
                $this->filter = $this->emptyExpression();
            } else {
                $this->filter->removeId($this->removeFilter);
            }
        }

        return $this;
    }


    protected function stripFilterIfRequested()
    {
        if ($this->stripFilter !== null) {
            $strip = $this->stripFilter;
            $subId = $strip . '-1';
            if ($this->filter->getId() === $strip) {
                $this->filter = $this->filter->getById($subId);
            } else {
                $this->filter->replaceById($strip, $this->filter->getById($subId));
            }
        }

        return $this;
    }

    protected function addNewFilterIfRequested()
    {
        if ($this->addTo !== null) {
            $parent = $this->filter->getById($this->addTo);

            if ($parent instanceof FilterChain) {
                if ($parent->isEmpty()) {
                    $parent->addFilter($this->emptyExpression());
                } else {
                    $parent->addFilter($this->emptyExpression());
                }
            } elseif ($parent instanceof FilterExpression) {
                $replacement = Filter::matchAll(clone($parent));
                if ($parent->isRootNode()) {
                    $this->filter = $replacement;
                } else {
                    $this->filter->replaceById($parent->getId(), $replacement);
                }
            }
        }

        return $this;
    }

    protected function fixNotsWithMultipleChildren()
    {
        $this->filter = $this->fixNotsWithMultipleChildrenForFilter($this->filter);
        return $this;
    }

    protected function fixNotsWithMultipleChildrenForFilter(Filter $filter)
    {
        if ($filter instanceof FilterChain) {
            if ($filter->getOperatorName() === 'NOT') {
                if ($filter->count() > 1) {
                    $filter = $this->notToNotAnd($filter);
                }
            }
            /** @var Filter $sub */
            foreach ($filter->filters() as $sub) {
                $filter->replaceById(
                    $sub->getId(),
                    $this->fixNotsWithMultipleChildrenForFilter($sub)
                );
            }
        }

        return $filter;
    }

    protected function notToNotAnd(FilterChain $not)
    {
        $and = Filter::matchAll();
        foreach ($not->filters() as $sub) {
            $and->addFilter(clone($sub));
        }

        return Filter::not($and);
    }

    protected function emptyExpression()
    {
        return Filter::expression('', '=', '');
    }

    protected function parentIdFor($id)
    {
        if (false === ($pos = strrpos($id, '-'))) {
            return '0';
        } else {
            return substr($id, 0, $pos);
        }
    }

    protected function idToFilterId($id)
    {
        if (! preg_match('/^id_(new_)?(\d+(?:-\d+)*)$/', $id, $m)) {
            die('nono' . $id);
        }

        return $m[2];
    }

    protected function checkEntryForActions($filterId, $entry)
    {
        switch ($this->entryAction($entry)) {
            case 'cancel':
                $this->removeFilter = $filterId;
                break;

            case 'minus':
                $this->stripFilter = $filterId;
                break;

            case 'plus':
            case 'angle-double-right':
                $this->addTo = $filterId;
                break;
        }
    }

    /**
     * Transforms a single submitted form component from an array
     * into a Filter object
     *
     * @param array $entry The array as submitted through the form
     *
     * @return Filter
     */
    protected function entryToFilter($entry)
    {
        if (array_key_exists('operator', $entry)) {
            return Filter::chain($entry['operator']);
        } else {
            return $this->entryToFilterExpression($entry);
        }
    }

    protected function entryToFilterExpression($entry)
    {
        if ($entry['sign'] === 'true') {
            return Filter::expression(
                $entry['column'],
                '=',
                $this->jsonEncode(true)
            );
        } elseif ($entry['sign'] === 'false') {
            return Filter::expression(
                $entry['column'],
                '=',
                $this->jsonEncode(false)
            );
        } elseif ($entry['sign'] === 'in') {
            if (array_key_exists('value', $entry)) {
                if (is_array($entry['value'])) {
                    $value = array_filter($entry['value'], 'strlen');
                } elseif (empty($entry['value'])) {
                    $value = array();
                } else {
                    $value = array($entry['value']);
                }
            } else {
                $value = array();
            }
            return Filter::expression(
                $entry['column'],
                '=',
                $this->jsonEncode($value)
            );
        } elseif ($entry['sign'] === 'contains') {
            $value = array_key_exists('value', $entry) ? $entry['value'] : null;

            return Filter::expression(
                $this->jsonEncode($value),
                '=',
                $entry['column']
            );
        } else {
            $value = array_key_exists('value', $entry) ? $entry['value'] : null;

            return Filter::expression(
                $entry['column'],
                $entry['sign'],
                $this->jsonEncode($value)
            );
        }
    }

    protected function jsonEncode($string)
    {
        return preg_replace(
            ['/&/u', '/\|/u', '/!/u', '/=/u', '/>/u', '/</u'],
            ['\u0026', '\u007c', '\u0021', '\u003d', '\u003e', '\u003c'],
            JsonString::encode($string)
        );
    }

    protected function entryAction($entry)
    {
        if (array_key_exists('action', $entry)) {
            return IconHelper::instance()->characterIconName($entry['action']);
        }

        return null;
    }

    protected function hasIncompleteExpressions(Filter $filter)
    {
        if ($filter instanceof FilterChain) {
            foreach ($filter->filters() as $sub) {
                if ($this->hasIncompleteExpressions($sub)) {
                    return true;
                }
            }

            return false;
        } else {
            /** @var FilterExpression $filter */
            if ($filter->isRootNode() && $this->isEmptyExpression($filter)) {
                return false;
            }

            return $filter->getColumn() === '';
        }
    }

    public function isValid($value, $context = null)
    {
        if (! $value instanceof Filter) {
            // TODO: try, return false on E
            $filter = $this->arrayToFilter($value);
            $this->setValue($filter);
        } else {
            $filter = $value;
        }

        if ($this->hasIncompleteExpressions($filter)) {
            $this->addError('The configured filter is incomplete');
            return false;
        }

        return parent::isValid($value);
    }
}
