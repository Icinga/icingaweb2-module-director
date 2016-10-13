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

            if ($value->isEmpty()) {
                $value = Filter::matchAll(Filter::expression('', '=', ''));
            }

        } catch (Exception $e) {
            $value = null;
            // TODO: getFile, getLine
            // Hint: cannot addMessage at it would loop through getValue
            $this->addErrorMessage($e->getMessage());
            $this->_isErrorForced = true;
        }
    }

    protected function arrayToFilter($array)
    {
        if ($array === null) {
            return Filter::matchAll();
        }

        $firstKey = key($array);
        if (! in_array($firstKey, array('id_1', 'id_new_0'))) {
            die('FCK: ' . key($array));
        }

        $entry = array_shift($array);
        $filter = $this->entryToFilter($entry);
        if ($firstKey === 'id_new_0') {
            $this->setAttrib('addTo', '0');
        }
   
        $remove = $strip = null;

        // TODO: This is for the first entry, duplicates code and has debug info
        $filterId = $this->idToFilterId($firstKey);
        switch ($this->entryAction($entry)) {
            case 'cancel':
                $remove = $filterId;
                echo "cancel";
                break;

            case 'minus':
                $strip = $filterId;
                echo "minus";
                break;

            case 'plus':
                $this->setAttrib('addTo', $filterId);
                echo "plus";
                break;
        }

        foreach ($array as $id => $entry) {
            // TODO: addTo from FilterEditor

            $sub = $this->entryToFilter($entry);
            $filterId = $this->idToFilterId($id);

            switch ($this->entryAction($entry)) {
                case 'cancel':
                    $remove = $filterId;
                    break;

                case 'minus':
                    $strip = $filterId;
                    break;

                case 'plus':
                    $this->setAttrib('addTo', $filterId);
                    break;
            }

            $parentId = $this->parentIdFor($filterId);
            $filter->getById($parentId)->addFilter($sub);
        }

        if ($remove) {
            if ($filter->getById($remove)->isRootNode()) {
                $filter = Filter::matchAll();
            } else {
                $filter->removeId($remove);
            }
        }

        if ($strip) {
            $subId = $strip . '-1';
            if ($filter->getId() === $strip) {
                $filter = $filter->getById($strip . '-1');
            } else {
                $filter->replaceById($strip, $filter->getById($strip . '-1'));
            }
        }

        return $filter;
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

    protected function entryToFilter($entry)
    {
        if (array_key_exists('operator', $entry)) {
            return Filter::chain($entry['operator']);
        } else {
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
