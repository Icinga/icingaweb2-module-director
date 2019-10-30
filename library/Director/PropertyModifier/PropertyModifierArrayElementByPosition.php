<?php

namespace Icinga\Module\Director\PropertyModifier;

use Icinga\Exception\ConfigurationError;
use Icinga\Module\Director\Hook\PropertyModifierHook;
use Icinga\Module\Director\Web\Form\QuickForm;
use InvalidArgumentException;
use stdClass;

class PropertyModifierArrayElementByPosition extends PropertyModifierHook
{
    public function getName()
    {
        return 'Get a specific Array Element';
    }

    public function hasArraySupport()
    {
        return true;
    }

    /**
     * @param QuickForm $form
     * @throws \Zend_Form_Exception
     */
    public static function addSettingsFormFields(QuickForm $form)
    {
        $form->addElement('select', 'position_type', [
            'label'       => $form->translate('Position Type'),
            'required'    => true,
            'multiOptions' => $form->optionalEnum([
                'first'   => $form->translate('First Element'),
                'last'    => $form->translate('Last Element'),
                'fixed'   => $form->translate('Specific Element (by position)'),
                'keyname' => $form->translate('Specific Element (by key name)'),
            ]),
        ]);

        $form->addElement('text', 'position', [
            'label'       => $form->translate('Position'),
            'description' => $form->translate(
                'Numeric position or key name'
            ),
        ]);

        $form->addElement('select', 'when_missing', [
            'label'       => $form->translate('When not available'),
            'required'    => true,
            'description' => $form->translate(
                'What should happen when the specified element is not available?'
            ),
            'value'        => 'null',
            'multiOptions' => $form->optionalEnum([
                'fail' => $form->translate('Let the whole Import Run fail'),
                'null' => $form->translate('return NULL'),
            ])
        ]);
    }

    /**
     * @param $value
     * @return string|null
     * @throws ConfigurationError
     * @throws InvalidArgumentException
     */
    public function transform($value)
    {
        // First and Last will work with hashes too:
        if ($value instanceof stdClass) {
            $value = (array) $value;
        }

        if (! is_array($value)) {
            return $this->emptyValue($value);
        }

        switch ($this->getSetting('position_type')) {
            case 'first':
                if (empty($value)) {
                    return $this->emptyValue($value);
                } else {
                    return array_shift($value);
                }
                // https://github.com/squizlabs/PHP_CodeSniffer/pull/1363
            case 'last':
                if (empty($value)) {
                    return $this->emptyValue($value);
                } else {
                    return array_pop($value);
                }
                // https://github.com/squizlabs/PHP_CodeSniffer/pull/1363
            case 'fixed':
                $pos = $this->getSetting('position');
                if (! is_int($pos) && ! ctype_digit($pos)) {
                    throw new InvalidArgumentException(sprintf(
                        '"%s" is not a valid array position',
                        $pos
                    ));
                }
                $pos = (int) $pos;

                if (array_key_exists($pos, $value)) {
                    return $value[$pos];
                } else {
                    return $this->emptyValue($value);
                }
                // https://github.com/squizlabs/PHP_CodeSniffer/pull/1363
            case 'keyname':
                $pos = $this->getSetting('position');
                if (! is_string($pos)) {
                    throw new InvalidArgumentException(sprintf(
                        '"%s" is not a valid array key name',
                        $pos
                    ));
                }

                if (array_key_exists($pos, $value)) {
                    return $value[$pos];
                } else {
                    return $this->emptyValue($value);
                }
                // https://github.com/squizlabs/PHP_CodeSniffer/pull/1363
            default:
                throw new ConfigurationError(
                    '"%s" is not a valid array position_type',
                    $this->getSetting('position_type')
                );
        }
    }

    /**
     * @return string
     * @throws ConfigurationError
     */
    protected function getPositionForError()
    {
        switch ($this->getSetting('position_type')) {
            case 'first':
                return 'first';
            case 'last':
                return 'last';
            case 'fixed':
                return '#' . $this->getSetting('position');
            case 'keyname':
                return '#' . $this->getSetting('position');
            default:
                throw new ConfigurationError(
                    '"%s" is not a valid array position_type',
                    $this->getSetting('position_type')
                );
        }
    }

    /**
     * @param $value
     * @return null
     * @throws ConfigurationError
     */
    protected function emptyValue($value)
    {
        if ($this->getSetting('when_missing', 'fail') === 'null') {
            return null;
        } else {
            throw new InvalidArgumentException(sprintf(
                'There is no %s element in %s',
                $this->getPositionForError(),
                json_encode($value)
            ));
        }
    }
}
