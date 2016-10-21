<?php

namespace Icinga\Module\Director\Web\Form\Element;

use Icinga\Data\Filter\Filter;
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

    protected $addTo;

    protected $removeFilter;

    protected $stripFilter;

    /**
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
            } else {
                $value = $this->arrayToFilter($value);
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
     */
    protected function arrayToFilter($array)
    {
        if ($array === null) {
            return null;
            return Filter::matchAll();
        }

        $filter = null;
        foreach ($array as $id => $entry) {
            $filterId = $this->idToFilterId($id);
            $sub = $this->entryToFilter($entry);
            $this->checkEntryForActions($filterId, $entry);
            $parentId = $this->parentIdFor($filterId);

            if ($filter === null) {
                $filter = $sub;
            } else {
                $filter->getById($parentId)->addFilter($sub);
            }
        }

        if ($this->removeFilter !== null) {
            if ($filter->getById($this->removeFilter)->isRootNode()) {
                $filter = $this->emptyExpression();
            } else {
                $filter->removeId($this->removeFilter);
            }
        }

        if ($this->stripFilter !== null) {
            $strip = $this->stripFilter;
            $subId = $strip . '-1';
            if ($filter->getId() === $strip) {
                $filter = $filter->getById($strip . '-1');
            } else {
                $filter->replaceById($strip, $filter->getById($strip . '-1'));
            }
        }

        if ($this->addTo !== null) {
            $parent = $filter->getById($this->addTo);

            if ($parent->isChain()) {
                if ($parent->isEmpty()) {
                    $parent->addFilter($this->emptyExpression());
                } elseif ($parent->getOperatorName() === 'NOT') {
                    $andNot = Filter::matchAll();
                    foreach ($parent->filters() as $sub) {
                        $andNot->addFilter(clone($sub));
                    }
                    $clone->addFilter($this->emptyExpression());
                    $filter->replaceById(
                        $parent->getId(),
                        Filter::not($andNot)
                    );
                } else {
                    $parent->addFilter($this->emptyExpression());
                }
            } else {
                $replacement = Filter::matchAll(clone($parent));
                if ($parent->isRootNode()) {
                    $filter = $replacement;
                } else {
                    $filter->replaceById($parent->getId(), $replacement);
                }
            }
        }

        return $filter;
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
     * @param Array $entry The array as submitted through the form
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
                true
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
                $value
            );
        } else {
            return Filter::expression(
                $entry['column'],
                $entry['sign'],
                array_key_exists('value', $entry) ? $entry['value'] : null
            );
        }
    }

    protected function entryAction($entry)
    {
        if (array_key_exists('action', $entry)) {
            return IconHelper::instance()->characterIconName($entry['action']);
        }

        return null;
    }

    public function isValid($value, $context = null)
    {
        if (! $value instanceof Filter) {
            // TODO: try, return false on E
            $filter = $this->arrayToFilter($value);
        }

        $this->setValue($filter);

        return true;
    }
}
