<?php

namespace Icinga\Module\Director\PropertyModifier;

use Icinga\Data\Filter\FilterExpression;
use Icinga\Exception\ConfigurationError;
use Icinga\Module\Director\Hook\PropertyModifierHook;
use Icinga\Module\Director\Web\Form\QuickForm;

class PropertyModifierRejectOrSelect extends PropertyModifierHook
{
    /** @var FilterExpression */
    private $filterExpression;

    public function getName()
    {
        return mt('director', 'Reject or keep rows based on property value');
    }

    /**
     * @inheritdoc
     * @throws \Zend_Form_Exception
     */
    public static function addSettingsFormFields(QuickForm $form)
    {
        $form->addElement('select', 'filter_method', [
            'label'       => $form->translate('Filter method'),
            'required'    => true,
            'value'        => 'wildcard',
            'multiOptions' => $form->optionalEnum([
                'wildcard' => $form->translate('Simple match with wildcards (*)'),
                'regex'    => $form->translate('Regular Expression'),
                'is_true'  => $form->translate('Match boolean TRUE'),
                'is_false' => $form->translate('Match boolean FALSE'),
                'is_null'  => $form->translate('Match NULL value columns'),
            ]),
            'class' => 'autosubmit',
        ]);

        $method = $form->getSetting('filter_method');
        switch ($method) {
            case 'wildcard':
                $form->addElement('text', 'filter_string', [
                    'label'       => $form->translate('Filter'),
                    'description' => $form->translate(
                        'The string/pattern you want to search for, use wildcard'
                        . ' matches like www.* or *linux*'
                    ),
                    'required'    => true,
                ]);
                break;
            case 'regex':
                $form->addElement('text', 'filter_string', [
                    'label'       => $form->translate('Filter'),
                    'description' => $form->translate(
                        'The string/pattern you want to search for, use regular'
                        . ' expression like /^www\d+\./'
                    ),
                    'required'    => true,
                ]);
                break;
        }

        $form->addElement('select', 'policy', [
            'label'       => $form->translate('Policy'),
            'required'    => true,
            'description' => $form->translate(
                'What should happen with the row, when this property matches the given expression?'
            ),
            'value'        => 'reject',
            'multiOptions' => [
                'reject' => $form->translate('Reject the whole row'),
                'keep'   => $form->translate('Keep only matching rows'),
            ],
        ]);
    }

    public function matchesRegexp($string, $expression)
    {
        if ($string === null) {
            $string = (string) $string;
        }
        return preg_match($expression, $string);
    }

    public function isNull($string, $expression)
    {
        return $string === null;
    }

    public function isTrue($string, $expression)
    {
        return $string === true;
    }

    public function isFalse($string, $expression)
    {
        return $string === false;
    }

    public function matchesWildcard($string, $expression)
    {
        return $this->filterExpression->matches(
            (object) ['value' => $string]
        );
    }

    public function transform($value)
    {
        $method = $this->getSetting('filter_method');
        $filter = $this->getSetting('filter_string');
        $policy = $this->getSetting('policy');

        switch ($method) {
            case 'wildcard':
                $func = 'matchesWildcard';
                $this->filterExpression = new FilterExpression('value', '=', $filter);
                break;
            case 'regex':
                $func = 'matchesRegexp';
                break;
            case 'is_null':
                $func = 'isNull';
                break;
            case 'is_true':
                $func = 'isTrue';
                break;
            case 'is_false':
                $func = 'isFalse';
                break;
            default:
                throw new ConfigurationError(
                    '%s is not a valid value for an ArrayFilter filter_method',
                    var_export($method, true)
                );
        }

        if ($this->$func($value, $filter)) {
            if ($policy === 'reject') {
                $this->rejectRow();
            }
        } else {
            if ($policy === 'keep') {
                $this->rejectRow();
            }
        }

        return $value;
    }
}
