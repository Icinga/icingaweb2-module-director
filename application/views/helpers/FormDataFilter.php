<?php

use Icinga\Data\Filter\Filter;
use Icinga\Data\Filter\FilterChain;
use Icinga\Data\Filter\FilterExpression;
use Icinga\Data\FilterColumns;
use Icinga\Module\Director\Objects\IcingaObject;
use Icinga\Module\Director\Web\Form\Element\Boolean;
use Icinga\Module\Director\Web\Form\IconHelper;

/**
 * View helper for extensible sets
 *
 * Avoid complaints about class names:
 * @codingStandardsIgnoreStart
 */
class Zend_View_Helper_FormDataFilter extends Zend_View_Helper_FormElement
{
    private $fieldName;

    private $cachedColumnSelect;

    private $query;

    private $suggestionContext;

    /**
     * Generates an 'extensible set' element.
     *
     * @codingStandardsIgnoreEnd
     *
     * @param string|array $name If a string, the element name.  If an
     * array, all other parameters are ignored, and the array elements
     * are used in place of added parameters.
     *
     * @param mixed $value The element value.
     *
     * @param array $attribs Attributes for the element tag.
     *
     * @return string The element XHTML.
     * @throws Zend_Form_Exception
     */
    public function formDataFilter($name, $value = null, $attribs = null)
    {
        $info = $this->_getInfo($name, $value, $attribs);
        extract($info); // id, name, value, attribs, options, listsep, disable
        if ($attribs) {
            if (array_key_exists('columns', $attribs)) {
                $this->setColumns($attribs['columns']);
                unset($attribs['columns']);
            }

            if (array_key_exists('suggestionContext', $attribs)) {
                $this->setSuggestionContext($attribs['suggestionContext']);
                unset($attribs['suggestionContext']);
            }
        }

        // TODO: check for columns in attribs, preserve & remove them from the
        // array use attribs? class etc? disabled?
        // override _getInfo?
        $this->fieldName = $name;

        if ($value === null) {
            $value = $this->emptyExpression();
        } elseif (is_string($value)) {
            $value = Filter::fromQueryString($value);
        }

        return $this->beginRoot()
            . $this->renderFilter($value)
            . $this->endRoot();
    }

    /**
     * @param Filter $filter
     * @return string
     * @throws Zend_Form_Exception
     */
    protected function renderFilter(Filter $filter)
    {
        if ($filter instanceof FilterChain) {
            return $this->renderFilterChain($filter);
        } elseif ($filter instanceof FilterExpression) {
            return $this->renderFilterExpression($filter);
        } else {
            throw new InvalidArgumentException('Got a Filter being neither expression nor chain');
        }
    }

    protected function beginRoot()
    {
        return '<ul class="filter-root">';
    }

    protected function endRoot()
    {
        return '</ul>';
    }

    /**
     * @param FilterChain $filter
     * @return string
     * @throws Zend_Form_Exception
     */
    protected function renderFilterChain(FilterChain $filter)
    {
        $parts = array();
        foreach ($filter->filters() as $f) {
            $parts[] = $this->renderFilter($f);
        }

        return $this->beginChain($filter)
            . implode('', $parts)
            . $this->endChain($filter);
    }

    protected function beginChain(FilterChain $filter)
    {
        $list = $filter->isEmpty() ? '' : '<ul>' . "\n";

        return '<li class="filter-chain"><span class="handle"> </span>'
             . $this->selectOperator($filter)
             . $this->removeLink($filter)
             . $this->addLink($filter)
             . ($filter->count() === 1 ? $this->stripLink($filter) : '')
             . $list;
    }

    protected function endChain(FilterChain $filter)
    {
        $list = $filter->isEmpty() ? '' : "</ul>\n";
        return $list . "</li>\n";
    }

    protected function beginExpression(FilterExpression $filter)
    {
        return '<div class="filter-expression">' . "\n";
    }

    protected function endExpression(FilterExpression $filter)
    {
        return "</div>\n";
    }

    protected function beginElement(FilterExpression $filter)
    {
        return '<div class="expression-wrapper">' . "\n";
    }

    protected function endElement(FilterExpression $filter)
    {
        return "</div>\n";
    }

    /**
     * @param FilterExpression $filter
     * @return string
     * @throws Zend_Form_Exception
     */
    protected function filterExpressionHtml(FilterExpression $filter)
    {
        return $this->selectColumn($filter)
             . $this->selectSign($filter)
             . $this->beginElement($filter)
             . $this->element($filter)
             . $this->endElement($filter)
             . $this->removeLink($filter)
             . $this->expandLink($filter);
    }

    /**
     * @param FilterExpression $filter
     * @return string
     * @throws Zend_Form_Exception
     */
    protected function renderFilterExpression(FilterExpression $filter)
    {
        return $this->beginExpression($filter)
             . $this->filterExpressionHtml($filter)
             . $this->endExpression($filter);
    }

    /**
     * @param ?FilterExpression $filter
     * @return Boolean|string
     * @throws Zend_Form_Exception
     */
    protected function element(?FilterExpression $filter = null)
    {
        if ($filter) {
            // TODO: Make this configurable
            $type = 'host';
            $prefixLen = strlen($type) + 1;
            $filter = clone($filter);
            $col = $filter->getColumn();

            if ($this->columnIsJson($filter)) {
                $col = $filter->getExpression();
                $filter->setExpression(json_decode($filter->getColumn()));
            } else {
                $filter->setExpression(json_decode($filter->getExpression()));
            }

            if (($filter->getExpression() === true) || ($filter->getExpression() === false)) {
                return '';
            }
            $dummy = IcingaObject::createByType($type);
            if ($dummy->hasProperty($col)) {
                if ($dummy->propertyIsBoolean($col)) {
                    return $this->boolean($filter);
                }
            }

            if (substr($col, -7) === '.groups' && $dummy->supportsGroups()) {
                $type = substr($col, 0, -7);

                return $this->selectGroup($type, $filter);
            } elseif (substr($col, $prefixLen, 5) === 'vars.') {
                $var = substr($col, $prefixLen + 5);

                return $this->text($filter, "DataListValues!{$var}");
            }
        }

        return $this->text($filter);
    }

    /**
     * @param $type
     * @param FilterExpression $filter
     * @return Zend_Form_Element
     */
    protected function selectGroup($type, FilterExpression $filter)
    {
        return $this->view->formText(
            $this->elementId('value', $filter),
            $filter->getExpression(),
            [
                'class' => 'director-suggest',
                'data-suggestion-context' => "{$type}groupnames",
            ]
        );
    }

    /**
     * @param ?FilterExpression $filter
     * @return Boolean
     * @throws Zend_Form_Exception
     */
    protected function boolean(?FilterExpression $filter = null)
    {
        $value = $filter === null ? '' : $filter->getExpression();

        $el = new Boolean(
            $this->elementId('value', $filter),
            array(
                'value'      => $value,
                'decorators' => array('ViewHelper'),
            )
        );

        return $el;
    }

    protected function columnIsJson(FilterExpression $filter)
    {
        $col = $filter->getColumn();
        return strlen($col) && $col[0] === '"';
    }

    /**
     * @param ?FilterExpression $filter
     * @param string $suggestionContext
     *
     * @return mixed
     */
    protected function text(?FilterExpression $filter = null, $suggestionContext = null)
    {
        $attr = null;
        if ($suggestionContext !== null) {
            $attr = [
                'class'                   => 'director-suggest',
                'data-suggestion-context' => $suggestionContext,
            ];
        }

        $value = $filter === null ? '' : $filter->getExpression();
        if (is_array($value)) {
            return $this->view->formIplExtensibleSet(
                $this->elementId('value', $filter),
                $value,
                $attr
            );
        }

        return $this->view->formText(
            $this->elementId('value', $filter),
            $value,
            $attr
        );
    }

    /**
     * @return \Icinga\Data\Filter\FilterExpression
     */
    protected function emptyExpression()
    {
        return Filter::expression('', '=', '');
    }

    protected function arrayForSelect($array, $flip = false)
    {
        $res = array();
        foreach ($array as $k => $v) {
            if (is_int($k)) {
                $res[$v] = ucwords(str_replace('_', ' ', $v));
            } elseif ($flip) {
                $res[$v] = $k;
            } else {
                $res[$k] = $v;
            }
        }

        // sort($res);
        return $res;
    }

    protected function elementId($field, ?Filter $filter = null)
    {
        $prefix = $this->fieldName . '[id_';
        $suffix = '][' . $field . ']';

        return $prefix . $filter->getId() . $suffix;
    }

    /**
     * @param ?FilterChain $filter
     * @return mixed
     */
    protected function selectOperator(?FilterChain $filter = null)
    {
        $ops = [
            'AND' => 'AND',
            'OR'  => 'OR',
            'NOT' => 'NOT'
        ];

        return $this->select(
            $this->elementId('operator', $filter),
            $ops,
            $filter === null ? null : $filter->getOperatorName(),
            ['class' => 'operator autosubmit']
        );
    }

    protected function selectSign(?FilterExpression $filter = null)
    {
        $signs = [
            '='  => '=',
            '!=' => '!=',
            '>'  => '>',
            '<'  => '<',
            '>=' => '>=',
            '<=' => '<=',
            'in' => 'in',
            'contains' => 'contains',
            'true'     => 'is true (or set)',
            'false'    => 'is false (or not set)',
        ];

        if ($filter === null) {
            $sign = null;
        } else {
            if ($this->columnIsJson($filter)) {
                $sign = 'contains';
            } else {
                $expression = json_decode($filter->getExpression());
                if ($expression === true) {
                    $sign = 'true';
                } elseif ($expression === false) {
                    $sign = 'false';
                } elseif (is_array($expression)) {
                    $sign = 'in';
                } else {
                    $sign = $filter->getSign();
                }
            }
        }

        $class = 'sign autosubmit';
        if (strlen($sign) > 3) {
            $class .= ' wide';
        }

        return $this->select(
            $this->elementId('sign', $filter),
            $signs,
            $sign,
            array('class' => $class)
        );
    }

    public function setColumns(?array $columns = null)
    {
        $this->cachedColumnSelect = $columns ? $this->arrayForSelect($columns) : null;
        return $this;
    }

    protected function getSuggestionContext()
    {
        return $this->suggestionContext;
    }

    protected function setSuggestionContext($context)
    {
        $this->suggestionContext = $context;
    }

    protected function selectColumn(?FilterExpression $filter = null)
    {
        $active = $filter === null ? null : $filter->getColumn();
        if ($filter && $this->columnIsJson($filter)) {
            $active = $filter->getExpression();
        }

        if ($context = $this->getSuggestionContext()) {
            return $this->view->formText(
                $this->elementId('column', $filter),
                $active,
                [
                    'class' => 'column autosubmit director-suggest',
                    'data-suggestion-context' => $context,
                ]
            );
        }
        if ($this->hasColumnList()) {
            $cols = $this->getColumnList();
            if ($active && !isset($cols[$active])) {
                $cols[$active] = str_replace(
                    '_',
                    ' ',
                    ucfirst(ltrim($active, '_'))
                ); // ??
            }

            $cols = $this->optionalEnum($cols);

            return $this->select(
                $this->elementId('column', $filter),
                $cols,
                $active,
                ['class' => 'column autosubmit']
            );
        } else {
            return $this->view->formText(
                $this->elementId('column', $filter),
                $active,
                ['class' => 'column autosubmit']
            );
        }
    }

    protected function optionalEnum($enum)
    {
        return array_merge(
            array(null => $this->view->translate('- please choose -')),
            $enum
        );
    }

    protected function hasColumnList()
    {
        return $this->cachedColumnSelect !== null || $this->query !== null;
    }

    protected function getColumnList()
    {
        if ($this->cachedColumnSelect === null) {
            $this->fetchColumnList();
        }

        return $this->cachedColumnSelect;
    }

    protected function fetchColumnList()
    {
        if ($this->query instanceof FilterColumns) {
            $this->cachedColumnSelect = $this->arrayForSelect(
                $this->query->getFilterColumns(),
                true
            );
            asort($this->cachedColumnSelect);
        } elseif ($this->cachedColumnSelect === null) {
            throw new RuntimeException('No columns set nor does the query provide any');
        }
    }

    protected function select($name, $list, $selected, $attributes = null)
    {
        return $this->view->formSelect($name, $selected, $attributes, $list);
    }

    protected function removeLink(Filter $filter)
    {
        return $this->filterActionButton(
            $filter,
            'cancel',
            t('Remove this part of your filter')
        );
    }

    protected function addLink(Filter $filter)
    {
        return $this->filterActionButton(
            $filter,
            'plus',
            t('Add another filter')
        );
    }

    protected function expandLink(Filter $filter)
    {
        return $this->filterActionButton(
            $filter,
            'angle-double-right',
            t('Wrap this expression into an operator')
        );
    }

    protected function stripLink(Filter $filter)
    {
        return $this->filterActionButton(
            $filter,
            'minus',
            t('Strip this operator, preserve child nodes')
        );
    }

    protected function filterActionButton(Filter $filter, $action, $title)
    {
        return $this->iconButton(
            $this->getActionButtonName($filter),
            $action,
            $title
        );
    }

    protected function getActionButtonName(Filter $filter)
    {
        return sprintf(
            '%s[id_%s][action]',
            $this->fieldName,
            $filter->getId()
        );
    }

    protected function iconButton($name, $icon, $title)
    {
        return $this->view->formSubmit(
            $name,
            IconHelper::instance()->iconCharacter($icon),
            array('class' => 'icon-button', 'title' => $title)
        );
    }
}
