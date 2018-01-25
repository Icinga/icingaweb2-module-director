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
        return 'Black or White-list rows based on property value';
    }

    public static function addSettingsFormFields(QuickForm $form)
    {
        $form->addElement('select', 'filter_method', [
            'label'       => $form->translate('Filter method'),
            'required'    => true,
            'value'        => 'wildcard',
            'multiOptions' => $form->optionalEnum([
                'wildcard' => $form->translate('Simple match with wildcards (*)'),
                'regex'    => $form->translate('Regular Expression'),
            ]),
        ]);

        $form->addElement('text', 'filter_string', [
            'label'       => 'Filter',
            'description' => $form->translate(
                'The string/pattern you want to search for. Depends on the'
                . ' chosen method, use www.* or *linux* for wildcard matches'
                . ' and expression like /^www\d+\./ in case you opted for a'
                . ' regular expression'
            ),
            'required'    => true,
        ]);

        $form->addElement('select', 'policy', [
            'label'       => $form->translate('Policy'),
            'required'    => true,
            'description' => $form->translate(
                'What should happen with the row, when this property matches the five expression?'
            ),
            'value'        => 'reject',
            'multiOptions' => [
                'reject' => $form->translate('Reject the whole row (Blacklist)'),
                'keep'   => $form->translate('Keep only matching rows (Whitelist)'),
            ],
        ]);
    }

    public function matchesRegexp($string, $expression)
    {
        return preg_match($expression, $string);
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
            default:
                throw new ConfigurationError(
                    '%s is not a valid value for an ArrayFilter filter_method',
                    var_export($method, 1)
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
