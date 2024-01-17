<?php

namespace Icinga\Module\Director\PropertyModifier;

use Icinga\Data\Filter\FilterExpression;
use Icinga\Exception\ConfigurationError;
use Icinga\Exception\InvalidPropertyException;
use Icinga\Module\Director\Hook\PropertyModifierHook;
use Icinga\Module\Director\Web\Form\QuickForm;

class PropertyModifierArrayFilter extends PropertyModifierHook
{
    /** @var FilterExpression */
    private $filterExpression;

    public function getName()
    {
        return 'Filter Array Values';
    }

    public function hasArraySupport()
    {
        return true;
    }

    public static function addSettingsFormFields(QuickForm $form)
    {
        $form->addElement('select', 'filter_method', array(
            'label'       => $form->translate('Filter method'),
            'required'    => true,
            'value'        => 'wildcard',
            'multiOptions' => $form->optionalEnum(array(
                'wildcard' => $form->translate('Simple match with wildcards (*)'),
                'regex'    => $form->translate('Regular Expression'),
            )),
        ));

        $form->addElement('text', 'filter_string', array(
            'label'       => 'Filter',
            'description' => $form->translate(
                'The string/pattern you want to search for. Depends on the'
                . ' chosen method, use www.* or *linux* for wildcard matches'
                . ' and expression like /^www\d+\./ in case you opted for a'
                . ' regular expression'
            ),
            'required'    => true,
        ));

        $form->addElement('select', 'policy', array(
            'label'       => $form->translate('Policy'),
            'required'    => true,
            'description' => $form->translate(
                'What should happen with matching elements?'
            ),
            'value'        => 'keep',
            'multiOptions' => array(
                'keep'   => $form->translate('Keep matching elements'),
                'reject' => $form->translate('Reject matching elements'),
            ),
        ));

        $form->addElement('select', 'when_empty', array(
            'label'       => $form->translate('When empty'),
            'required'    => true,
            'description' => $form->translate(
                'What should happen when the result array is empty?'
            ),
            'value'        => 'empty_array',
            'multiOptions' => $form->optionalEnum(array(
                'empty_array' => $form->translate('return an empty array'),
                'null'        => $form->translate('return NULL'),
            ))
        ));
    }

    public function matchesRegexp($string, $expression)
    {
        return preg_match($expression, $string);
    }

    public function matchesWildcard($string, $expression)
    {
        return $this->filterExpression->matches(
            (object) array('value' => $string)
        );
    }

    public function transform($value)
    {
        if (empty($value)) {
            return $this->emptyValue();
        }

        if (is_string($value)) {
            $value = [$value];
        }

        if (! is_array($value)) {
            throw new InvalidPropertyException(
                'The ArrayFilter property modifier be applied to arrays only'
            );
        }

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
            default:
                throw new ConfigurationError(
                    '%s is not a valid value for an ArrayFilter filter_method',
                    var_export($method, true)
                );
        }

        $result = array();

        foreach ($value as $val) {
            if ($this->$func($val, $filter)) {
                if ($policy === 'keep') {
                    $result[] = $val;
                }
            } else {
                if ($policy === 'reject') {
                    $result[] = $val;
                }
            }
        }

        if (empty($result)) {
            return $this->emptyValue();
        }

        return $result;
    }

    protected function emptyValue()
    {
        if ($this->getSetting('when_empty', 'empty_array') === 'empty_array') {
            return array();
        } else {
            return null;
        }
    }
}
