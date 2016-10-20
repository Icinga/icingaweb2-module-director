<?php

namespace Icinga\Module\Director\Web\Form\Element;

class Dictionary extends FormElement
{
    public $helper = 'formDictionary';
    private $structure = null;
    private $fieldSettingsMap = [];

    public function isValid($value, $context = null)
    {
        if (is_string($value)) {
            $value = json_decode($value, true);
        }
        return $this->validateNode($this->structure, $value);
    }

    protected function validateNode($structure, $value, $keyPrefix = '')
    {
        if ($value === null) {
            return true;
        }
        foreach ($structure as $key => $node) {
            $fullKey = $keyPrefix . $key;

            if ( ! key_exists($key, $value)) {
                $this->addError(sprintf("Key '%s' is missing", $fullKey));
                return false;
            }

            if (is_array($value[$key]) && ! $this->validateNode($node, $value[$key], $fullKey . '.')) {
                return false;
            }

            if ($this->fieldSettingsMap[$fullKey]['is_required'] && $value[$key] === null) {
                $this->addError(sprintf("Key '%s' is required and cannot be NULL", $fullKey));
                return false;
            }

            $expectedType = gettype($structure[$key]);
            $currentType = gettype($value[$key]);
            if ($expectedType !== $currentType && $value[$key] !== null) {
                $this->addError(sprintf("Type mismatch, '%s' is expected to be a '%s', '%s' given",
                    $fullKey, $expectedType, $currentType));
                return false;
            }
        }
        return true;
    }

    public function setDefaultValue($value)
    {
        $this->structure = $value;
        $this->setValue($value);
    }

    public function setFieldSettingsMap($fieldSettingsMap)
    {
        $this->fieldSettingsMap = $fieldSettingsMap;
    }

    public function getFieldSettingsMap()
    {
        return $this->fieldSettingsMap;
    }

    public function setValue($value)
    {
        if (is_string($value)) {
            $value = json_decode($value, true);
        }
        if ($value instanceof \stdClass) {
            $value = json_decode(json_encode($value), true);
        }
        return parent::setValue($value);
    }

    protected function _getErrorMessages()
    {
        $translator = $this->getTranslator();
        $messages   = $this->getErrorMessages();
        $value      = $this->getValue();
        foreach ($messages as $key => $message) {
            if (null !== $translator) {
                $message = $translator->translate($message);
            }
            $messages[$key] = str_replace('%value%', json_encode($value), $message);
        }
        return $messages;

    }

    public function getValue()
    {
        $value = parent::getValue();
        return $value;
    }
}

